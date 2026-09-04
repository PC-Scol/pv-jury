<?php
namespace app;

use nulib\A;
use nulib\cl;
use nulib\exceptions;
use nulib\ext\tab\SsReader;
use nulib\file\web\Upload;
use nulib\os\path;
use nulib\str;
use nulib\ValueException;
use stdClass;

/**
 * Class PvDataExtractor: extraire les données d'un fichier "PV de Jury" édité
 * depuis PEGASE
 */
class PvDataExtractor {
  static function invalid_file(): ValueException {
    return new ValueException("Ce fichier ne semble pas être un PV de jury valide");
  }
  static function parse1_title(array $row, array &$data, &$ctx): bool {
    if ($ctx === null) {
      if (!str::starts_with("Pv de jury", cl::first($row))) {
        throw self::invalid_file();
      }
      $ctx = 1;
    }
    if ($ctx >= 4 && cl::all_n($row)) {
      # il faut au moins 4 lignes de titre
      return true;
    }
    $data["title"][] = implode(" ", array_filter($row));
    $ctx++;
    return false;
  }

  static function parse2_gpts(array $row, array &$data): bool {
    if (!cl::all_n($row)) $data["headers"][] = $row;
    array_splice($row, 0, 3);
    $data["gpts"] = $row;
    return true;
  }

  static function parse3_objs(array $row, array &$data): bool {
    $c = new class($data) extends stdClass {
      public array $data;
      public array $gpts;
      public int $igpt = 0;
      public bool $newObj = true;
      public ?array $objs;
      public int $iobj = 0;
      public ?array $obj = null;

      function __construct(array &$data) {
        $this->data =& $data;
        $this->gpts = $data["gpts"];
        $this->objs =& $data["objs"];
      }

      function new($col): bool {
        if (!$this->newObj) return false;
        $this->obj = [
          "iobj" => $this->iobj++,
          "title" => $col,
          "gpt_parent" => false,
          "gpt_count" => 0,
          "gpt_title" => $this->gpts[$this->igpt],
          "size" => 1,
        ];
        $this->igpt++;
        $this->newObj = false;
        return true;
      }

      function grow(): void {
        $this->igpt++;
        $this->obj["size"]++;
      }

      function shouldCommit($col): bool {
        return $col !== null;
      }

      function commit(): void {
        if ($this->newObj) return;
        $this->objs[] = $this->obj;
        $this->newObj = true;
        $this->obj = null;
      }
    };

    $data["headers"][] = $row;
    array_splice($row, 0, 3);
    foreach ($row as $col) {
      if ($c->new($col)) continue;
      if ($c->shouldCommit($col)) {
        $c->commit();
        $c->new($col);
      } else {
        $c->grow();
      }
    }
    $c->commit();

    # mettre à jour gpt_parent et gpt_count
    $haveGpts = false;
    $parentObj = null;
    $prevObj = null;
    foreach ($data["objs"] as &$obj) {
      if ($obj["gpt_title"] !== null) $haveGpts = true;
      if ($prevObj !== null && $prevObj["gpt_title"] === null && $obj["gpt_title"] !== null) {
        $prevObj["gpt_parent"] = true;
        $parentObj =& $prevObj;
        $parentObj["gpt_count"]++;
      } elseif ($prevObj !== null && $prevObj["gpt_title"] !== null && $obj["gpt_title"] !== null) {
        $parentObj["gpt_count"]++;
      } else {
        unset($parentObj);
        $parentObj = null;
      }
      $prevObj =& $obj;
    }; unset($obj);
    $data["have_gpts"] = $haveGpts;

    return true;
  }

  static function parse4_sess(array $row, array &$data): bool {
    $c = new class($data) extends stdClass {
      public array $data;
      public int $iobj;
      public ?array $obj;
      public bool $newSes = true;
      public ?array $ses = null;
      public int $ises = 0;
      public int $wses = 0;

      function __construct(array &$data) {
        $this->data =& $data;
        $this->iobj = 0;
        $this->obj =& $this->data["objs"][$this->iobj];
      }

      function new($col): bool {
        if (!$this->newSes) return false;
        $this->ses = [
          "title" => $col,
          "size" => 1,
          "have_value" => false,
          "have_note" => false,
          "have_res" => false,
          "is_acquis" => false,
          "acquis_col" => null,
          "is_session_n" => false,
          "is_session_f" => false,
          "is_session" => false,
          "cols" => null,
          "types" => null,
          "rev_cols" => null,
          "agg_cols" => null,
          "note_col" => null,
          "res_col" => null,
          "ects_col" => null,
          "pj_col" => null,
          "is_controle" => false,
        ];
        $this->wses++;
        $this->newSes = false;
        return true;
      }

      function grow(): void {
        $this->ses["size"]++;
        $this->wses++;
      }

      function shouldCommit($col): bool {
        return $col !== null || $this->wses >= $this->obj["size"];
      }

      function commit(bool $next=true): void {
        if ($this->newSes) return;

        $ses =& $this->ses;
        $sesTitle = $ses["title"];
        $isSessionN = $ses["is_session_n"] = str::starts_with("Session ", $sesTitle);
        $isSessionF = $ses["is_session_f"] = $sesTitle === "Evaluations Finales";
        $ses["is_session"] = $isSessionN || $isSessionF;
        $isControle = $ses["is_controle"] = str::starts_with("Contrôle ", $sesTitle);

        $ises = $this->ises++;
        $this->obj["sess"][$ises] = $this->ses;

        $sesTitle = $this->ses["title"];
        if ($isControle) {
          $ctlCols =& $this->data["ctl_cols"];
          if (!isset($ctlCols[$sesTitle])) {
            $ctlCols[$sesTitle] = ["title" => $sesTitle];
          }
        } else {
          $sesCols =& $this->data["ses_cols"];
          if (!isset($sesCols[$ises])) {
            $sesCols[$ises] = ["title" => $sesTitle];
          }
        }

        $this->newSes = true;
        $this->ses = null;
        if ($next && $this->wses >= $this->obj["size"]) {
          $this->obj =& $this->data["objs"][++$this->iobj];
          $this->ises = 0;
          $this->wses = 0;
        }
      }
    };

    $data["headers"][] = $row;
    array_splice($row, 0, 3);
    foreach ($row as $col) {
      if ($c->new($col)) continue;
      if ($c->shouldCommit($col)) {
        $c->commit();
        $c->new($col);
      } else {
        $c->grow();
      }
    }
    $c->commit(false);
    return true;
  }

  static function parse5_cols(array $row, array &$data): bool {
    $c = new class($data) extends stdClass {
      public array $data;
      public int $xobj = 0;
      public ?array $obj;
      public int $xses = 0;
      public ?array $ses;

      function __construct(array &$data) {
        $this->data =& $data;
        $this->obj =& $this->data["objs"][$this->xobj];
        $this->ses =& $this->obj["sess"][$this->xses];
      }

      function addCol($col, int $colIndex): void {
        if (str::starts_with("Amngt/Acquis", $col)) {
          $this->ses["acquis_col"] = $col;
          $this->ses["types"]["acquis"] = true;
          $this->ses["agg_cols"]["acquis"][] = $col;
          $this->ses["rev_cols"][$col] = [
            "type" => "acquis",
            "index" => $colIndex,
          ];
        } elseif ($col === "Note Finale") {
          # la note finale prime sur les autres
          $this->ses["note_col"] = $col;
          $this->ses["types"]["note"] = true;
          $this->ses["agg_cols"]["note"][] = $col;
          $this->ses["rev_cols"][$col] = [
            "type" => "note",
            "index" => $colIndex,
          ];
        } elseif ($col === "Note" || str::starts_with("Note ", $col)) {
          $this->ses["note_col"] ??= $col;
          $this->ses["types"]["note"] = true;
          $this->ses["agg_cols"]["note"][] = $col;
          $this->ses["rev_cols"][$col] = [
            "type" => "note",
            "index" => $colIndex,
          ];
        } elseif ($col === "Résultat Final") {
          # le résultat final prime sur les autres
          $this->ses["res_col"] = $col;
          $this->ses["types"]["res"] = true;
          $this->ses["agg_cols"]["res"][] = $col;
          $this->ses["rev_cols"][$col] = [
            "type" => "res",
            "index" => $colIndex,
          ];
        } elseif ($col === "Résultat" || str::starts_with("Résultat ", $col)) {
          $this->ses["res_col"] ??= $col;
          $this->ses["types"]["res"] = true;
          $this->ses["agg_cols"]["res"][] = $col;
          $this->ses["rev_cols"][$col] = [
            "type" => "res",
            "index" => $colIndex,
          ];
        } elseif ($col === "ECTS Finaux") {
          # les ECTS finaux priment sur les autres
          $this->ses["ects_col"] = $col;
          $this->ses["types"]["ects"] = true;
          $this->ses["agg_cols"]["ects"][] = $col;
          $this->ses["rev_cols"][$col] = [
            "type" => "ects",
            "index" => $colIndex,
          ];
        } elseif ($col === "ECTS" || str::starts_with("ECTS ", $col)) {
          $this->ses["ects_col"] ??= $col;
          $this->ses["types"]["ects"] = true;
          $this->ses["agg_cols"]["ects"][] = $col;
          $this->ses["rev_cols"][$col] = [
            "type" => "ects",
            "index" => $colIndex,
          ];
        } elseif ($col === "Points Jury Retenus") {
          # les points jury retenus priment sur les autres
          $this->ses["pj_col"] = $col;
          $this->ses["types"]["pj"] = true;
          $this->ses["agg_cols"]["pj"][] = $col;
          $this->ses["rev_cols"][$col] = [
            "type" => "pj",
            "index" => $colIndex,
          ];
        } elseif ($col === "Points Jury" || str::starts_with("Points Jury ", $col)) {
          $this->ses["pj_col"] ??= $col;
          $this->ses["types"]["pj"] = true;
          $this->ses["agg_cols"]["pj"][] = $col;
          $this->ses["rev_cols"][$col] = [
            "type" => "pj",
            "index" => $colIndex,
          ];
        } elseif ($col === "Barème" || str::starts_with("Barème ", $col)) {
          $this->ses["types"]["bareme"] = true;
          $this->ses["agg_cols"]["bareme"][] = $col;
          $this->ses["rev_cols"][$col] = [
            "type" => "bareme",
            "index" => $colIndex,
          ];
        } elseif ($col === "Coefficient") {
          $this->ses["types"]["coeff"] = true;
          $this->ses["agg_cols"]["coeff"][] = $col;
          $this->ses["rev_cols"][$col] = [
            "type" => "coeff",
            "index" => $colIndex,
          ];
        } elseif ($col === "Mention") {
          $this->ses["types"]["mention"] = true;
          $this->ses["agg_cols"]["mention"][] = $col;
          $this->ses["rev_cols"][$col] = [
            "type" => "mention",
            "index" => $colIndex,
          ];
        } elseif ($col === "Rang Final" || str::starts_with("Rang Final ", $col)) {
          # le rang final prime sur les autres
          $actualCol = $col;
          $col = "Rang Final";
          $this->ses["types"]["rang"] = true;
          $this->ses["agg_cols"]["rang"][] = $col;
          $this->ses["rev_cols"][$col] = $this->ses["rev_cols"][$actualCol] = [
            "type" => "rang",
            "index" => $colIndex,
            "col" => $col,
            "actual_col" => $actualCol,
          ];
        } elseif ($col === "Rang" || str::starts_with("Rang ", $col)) {
          $actualCol = $col;
          $col = "Rang";
          $this->ses["types"]["rang"] = true;
          $this->ses["agg_cols"]["rang"][] = $col;
          $this->ses["rev_cols"][$col] = $this->ses["rev_cols"][$actualCol] = [
            "type" => "rang",
            "index" => $colIndex,
            "col" => $col,
            "actual_col" => $actualCol,
          ];
        } else {
          $this->ses["types"]["divers"] = true;
          $this->ses["agg_cols"]["divers"][] = $col;
          $this->ses["rev_cols"][$col] = [
            "type" => "divers",
            "index" => $colIndex,
          ];
        }
        $this->ses["cols"][] = $col;

        if (count($this->ses["cols"]) >= $this->ses["size"]) {
          $this->ses["types"] = array_keys($this->ses["types"]);

          $this->xses++;
          $updateRefs = true;
          if ($this->xses >= count($this->obj["sess"])) {
            $this->xses = 0;
            $this->xobj++;
            if ($this->xobj >= count($this->data["objs"])) {
              $updateRefs = false;
            }
          }
          if ($updateRefs) {
            $this->obj =& $this->data["objs"][$this->xobj];
            $this->ses =& $this->obj["sess"][$this->xses];
          }
        }
      }
    };

    # Renommer les colonnes Barème avec le nom de la colonne note précédente
    $prevNoteCol = null;
    foreach ($row as &$col) {
      if ($col === "Note" || str::starts_with("Note ", $col)) {
        $prevNoteCol = $col;
      } elseif ($col === "Barème" && $prevNoteCol !== null) {
        $col .= " $prevNoteCol";
      }
    }; unset($col);
    $data["headers"][] = $row;
    array_splice($row, 0, 3);
    $sindex = 3;
    foreach ($row as $col) {
      $c->addCol($col, $sindex++);
    }
    return true;
  }

  static function parse6_row(array $row, array &$data): void {
    $codApr = $row[0];
    $sindex = 3;
    foreach ($data["objs"] as &$obj) {
      foreach ($obj["sess"] as &$ses) {
        $noteCol = $ses["note_col"];
        $resCol = $ses["res_col"];
        foreach ($ses["cols"] as $col) {
          $value = $row[$sindex];
          if ($value === "-") $row[$sindex] = $value = null;
          # ne pas considérer Barème quand il s'agit de décider s'il y a une
          # valeur
          if ($col === "Barème" || str::starts_with("Barème ", $col)) {
            $isValue = false;
          } else {
            $isValue = $value !== null;
          }
          $ses["have_value"] = $ses["have_value"] || $isValue;
          $haveNote = $col === $noteCol && $isValue;
          $ses["have_note"] = $ses["have_note"] || $haveNote;
          $haveRes = $col === $resCol && $isValue;
          $ses["have_res"] = $ses["have_res"] || $haveRes;
          $sindex++;
        }
      }; unset($ses);
    }; unset($obj);
    $data["rows"][$codApr] = $row;
  }

  const BOOL_COLS = ["have_value", "have_note", "have_res", "is_acquis", "is_session", "is_controle"];
  const SCALAR_COLS = ["acquis_col", "note_col", "res_col", "ects_col", "pj_col"];
  const ARRAY_COLS = ["cols", "types"];

  protected static function merge_cols(?array &$destCols, array $ses): void {
    $boolCols = self::BOOL_COLS;
    $scalarCols = self::SCALAR_COLS;
    $arrayCols = self::ARRAY_COLS;
    $allCols = array_merge($boolCols, $scalarCols, $arrayCols);
    if (!isset($destCols["cols"])) {
      # première fois: prendre en l'état
      A::merge($destCols, cl::select($ses, $allCols));
      $destCols["rev_cols"] = $ses["rev_cols"];
    } else {
      # fois suivantes, merger si modifications
      foreach ($boolCols as $col) {
        if ($ses[$col]) $destCols[$col] = true;
      }
      foreach ($scalarCols as $col) {
        $destCols[$col] ??= $ses[$col];
      }
      foreach ($arrayCols as $col) {
        $pvalues = $destCols[$col];
        $values = $ses[$col];
        if ($values !== $pvalues) {
          $pvalues = array_fill_keys($pvalues ?? [], true);
          $values = array_fill_keys($values ?? [], true);
          $values = array_merge($pvalues, $values);
          $destCols[$col] = array_keys($values);
        }
      }
      A::merge($destCols["rev_cols"], $ses["rev_cols"]);
    }
  }

  static function update_metadata(array &$data): void {
    $sesCols =& $data["ses_cols"];
    $ctlCols =& $data["ctl_cols"];
    foreach ($data["objs"] as &$obj) {
      $cses = null;
      $pses = null;
      foreach ($obj["sess"] as $ises => &$ses) {
        $title = $ses["title"];
        $isAcquis = $ses["is_acquis"] = $title === null && $ses["acquis_col"] !== null;
        $isSession = $ses["is_session"];
        $isControle = $ses["is_controle"];
        if ($isSession) {
          if ($cses !== null) $pses =& $cses;
          $cses =& $ses;
        }
        if ($isAcquis || $isSession) self::merge_cols($sesCols[$ises], $ses);
        if ($isControle) self::merge_cols($ctlCols[$title], $ses);
        if ($cses !== null) {
          if ($isControle) $cses["ctls"][] = $ses;
          if ($pses !== null && $pses["is_session_n"]) $pses["nses"] = $ses;
        }
      }; unset($ses);
      unset($cses);
      unset($pses);
    }; unset($obj);
  }

  function extract($input): PvData {
    if ($input instanceof Upload) {
      $origname = path::filename($input->fullPath);
    } elseif (is_string($input)) {
      $origname = path::filename($input);
    } else {
      throw exceptions::invalid_value($input, "file");
    }
    $name = pvs::basename($origname);
    $date = pvs::get_date($origname);

    $reader = SsReader::with($input, [
      "all_null_is_empty_row" => false,
      "ignore_empty_rows" => false,
      "use_headers" => false,
      "parse_none" => true,
    ]);
    $maxCols = 0;
    foreach ($reader as $row) {
      # ne pas tenir compte des colonnes nulles à la fin
      while (count($row) > 0 && $row[$lastKey = array_key_last($row)] === null) {
        unset($row[$lastKey]);
      }
      $count = count($row);
      if ($count > $maxCols) $maxCols = $count;
    }
    $data = [
      "origname" => $origname,
      "name" => $name,
      "date" => $date,
      "have_gpts" => false,
      "gpts" => null,
      "objs" => null,
      "ses_cols" => null,
      "ctl_cols" => null,
      "title" => null,
      "headers" => null,
      "rows" => null,
    ];
    $state = 10;
    foreach ($reader as $row) {
      A::ensure_size($row, $maxCols);
      if ($state == 10 && self::parse1_title($row, $data, $ctx1)) {
      #  $state = 11;
      #} elseif ($state == 11) {
        $state = 20;
      } elseif ($state == 20 && self::parse2_gpts($row, $data)) {
        $state = 30;
      } elseif ($state == 30 && self::parse3_objs($row, $data)) {
        $state = 40;
      } elseif ($state == 40 && self::parse4_sess($row, $data)) {
        $state = 50;
      } elseif ($state == 50 && self::parse5_cols($row, $data)) {
        $state = 60;
      } elseif ($state == 60) {
        self::parse6_row($row, $data);
      }
    }
    if ($data["objs"] === null) throw self::invalid_file();
    self::update_metadata($data);

    return new PvData($data);
  }
}

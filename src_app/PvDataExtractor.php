<?php
namespace app;

use nulib\A;
use nulib\cl;
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
  static function parse1_title(array $row, array &$data, &$ctx): bool {
    if ($ctx === null) $ctx = 1;
    if ($ctx >=4 && cl::all_n($row)) {
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

    if (!cl::all_n($row)) $data["headers"][] = $row;
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

    if (!cl::all_n($row)) $data["headers"][] = $row;
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
        } elseif ($col === "Note" || $col === "Note Retenue" || $col === "Note Finale") {
          $this->ses["note_col"] = $col;
        } elseif ($col === "Résultat" || $col === "Résultat Final") {
          $this->ses["res_col"] = $col;
        } elseif ($col === "ECTS" || $col === "ECTS Finaux") {
          $this->ses["ects_col"] = $col;
        } elseif ($col === "Points Jury" || $col === "Points Jury Retenus") {
          $this->ses["pj_col"] = $col;
        }
        $cols =& $this->ses["cols"];
        $cols[] = $col;
        $this->ses["col_indexes"][$col] = $colIndex;

        if (count($cols) >= $this->ses["size"]) {
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

    if (!cl::all_n($row)) $data["headers"][] = $row;
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
          if ($col === "Barème") $isValue = false;
          else $isValue = $value !== null;
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
        if (($isAcquis || $isSession) && !isset($sesCols[$ises]["cols"])) {
          A::merge($sesCols[$ises], cl::select($ses, [
            "have_value", "have_note", "have_res",
            "is_acquis",
            "acquis_col",
            "is_session",
            "note_col", "res_col", "ects_col", "pj_col",
            "is_controle",
            "cols",
          ]));
        }
        if ($isControle && !isset($ctlCols[$title]["cols"])) {
          A::merge($ctlCols[$title], cl::select($ses, [
            "have_value", "have_note", "have_res",
            "is_acquis",
            "acquis_col",
            "is_session",
            "note_col", "res_col", "ects_col", "pj_col",
            "is_controle",
            "cols",
          ]));
        }
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
      throw ValueException::invalid_kind($input, "file");
    }
    $name = pvs::basename($origname);
    $date = pvs::get_date($origname);

    $reader = SsReader::with($input, [
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
    $state = 1;
    foreach ($reader as $row) {
      A::ensure_size($row, $maxCols);
      if ($state == 1 && self::parse1_title($row, $data, $ctx1)) {
        $state = 2;
      } elseif ($state == 2 && self::parse2_gpts($row, $data)) {
        $state = 3;
      } elseif ($state == 3 && self::parse3_objs($row, $data)) {
        $state = 4;
      } elseif ($state == 4 && self::parse4_sess($row, $data)) {
        $state = 5;
      } elseif ($state == 5 && self::parse5_cols($row, $data)) {
        $state = 6;
      } elseif ($state == 6) {
        self::parse6_row($row, $data);
      }
    }
    self::update_metadata($data);

    return new PvData($data);
  }
}

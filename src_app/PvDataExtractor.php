<?php
namespace app;

use nur\sery\A;
use nur\sery\cl;
use nur\sery\ext\spreadsheet\SsReader;
use nur\sery\file\web\Upload;
use nur\sery\os\path;
use nur\sery\str;
use nur\sery\ValueException;
use stdClass;

/**
 * Class PvJuryExtractor: extraire les données d'un fichier "PV de Jury" édité
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
    $c = new class($data) extends stdClass {
      public array $data;
      public bool $newGpt = true;
      public ?array $gpt = null;

      function __construct(array &$data) {
        $this->data =& $data;
      }

      function new($col): bool {
        if (!$this->newGpt) return false;
        $this->gpt = [
          "title" => $col,
          "size" => 1,
        ];
        $this->newGpt = false;
        return true;
      }

      function grow(): void {
        $this->gpt["size"]++;
      }

      function shouldCommit($col): bool {
        return $col !== null;
      }

      function commit(): void {
        if ($this->newGpt) return;
        $this->data["gpts"][] = $this->gpt;
        $this->data["gpt_objs"][] = [
          "title" => $this->gpt["title"],
          "objs" => [],
        ];
        $this->newGpt = true;
        $this->gpt = null;
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
    return true;
  }

  static function parse3_objs(array $row, array &$data): bool {
    $c = new class($data) extends stdClass {
      public array $data;
      public int $igpt;
      public ?array $gpt;
      public ?array $gptsObjs;
      public bool $newObj = true;
      public ?array $obj = null;
      public int $wobj = 0;

      function __construct(array &$data) {
        $this->data =& $data;
        $this->igpt = 0;
        $this->gpt =& $this->data["gpts"][$this->igpt];
        $this->gptsObjs =& $this->data["gpt_objs"][$this->igpt];
      }

      function new($col): bool {
        if (!$this->newObj) return false;
        $this->obj = [
          "title" => $col,
          "size" => 1,
        ];
        $this->wobj++;
        $this->newObj = false;
        return true;
      }

      function grow(): void {
        $this->obj["size"]++;
        $this->wobj++;
      }

      function shouldCommit($col): bool {
        return $col !== null;
      }

      function commit(bool $next=true): void {
        if ($this->newObj) return;
        $this->gpt["objs"][] = $this->obj;
        $this->gptsObjs["objs"][] = $this->obj["title"];
        $this->newObj = true;
        $this->obj = null;
        if ($next && $this->wobj >= $this->gpt["size"]) {
          $igpt = ++$this->igpt;
          $this->gpt =& $this->data["gpts"][$igpt];
          $this->gptsObjs =& $this->data["gpt_objs"][$igpt];
          $this->wobj = 0;
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

  static function parse4_sess(array $row, array &$data): bool {
    $c = new class($data) extends stdClass {
      public array $data;
      public int $igpt;
      public ?array $gpt;
      public int $iobj;
      public ?array $obj;
      public int $wobj = 0;
      public bool $newSes = true;
      public ?array $ses = null;
      public int $ises = 0;
      public int $wses = 0;

      function __construct(array &$data) {
        $this->data =& $data;
        $this->igpt = 0;
        $this->gpt =& $this->data["gpts"][$this->igpt];
        $this->iobj = 0;
        $this->obj =& $this->gpt["objs"][$this->iobj];
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
          "is_session" => false,
          "note_col" => null,
          "res_col" => null,
          "ects_col" => null,
          "pj_col" => null,
        ];
        $this->wobj++;
        $this->wses++;
        $this->newSes = false;
        return true;
      }

      function grow($col): void {
        $this->ses["size"]++;
        $this->wobj++;
        $this->wses++;
      }

      function shouldCommit($col): bool {
        return $col !== null
          || $this->wobj >= $this->gpt["size"]
          || $this->wses >= $this->obj["size"];
      }

      function commit(bool $next=true): void {
        if ($this->newSes) return;
        $ises = $this->ises++;
        $this->obj["sess"][$ises] = $this->ses;
        $sesCols =& $this->data["ses_cols"];
        if (!isset($sesCols[$ises])) {
          $sesCols[$ises] = [
            "title" => $this->ses["title"],
          ];
        }
        $this->newSes = true;
        $this->ses = null;
        if ($next) {
          if ($this->wobj >= $this->gpt["size"]) {
            $this->gpt =& $this->data["gpts"][++$this->igpt];
            $this->obj =& $this->gpt["objs"][$this->iobj = 0];
            $this->wobj = 0;
            $this->ises = 0;
            $this->wses = 0;
          } elseif ($this->wses >= $this->obj["size"]) {
            $this->obj =& $this->gpt["objs"][++$this->iobj];
            $this->ises = 0;
            $this->wses = 0;
          }
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
        $c->grow($col);
      }
    }
    $c->commit(false);
    return true;
  }

  static function parse5_cols(array $row, array &$data): bool {
    $c = new class($data) extends stdClass {
      public array $data;
      public int $xgpt = 0;
      public ?array $gpt;
      public int $xobj = 0;
      public ?array $obj;
      public int $xses = 0;
      public ?array $ses;

      function __construct(array &$data) {
        $this->data =& $data;
        $this->gpt =& $this->data["gpts"][$this->xgpt];
        $this->obj =& $this->gpt["objs"][$this->xobj];
        $this->ses =& $this->obj["sess"][$this->xses];
      }

      function addCol($col, int $colIndex): void {
        if (str::starts_with("Amngt/Acquis", $col)) {
          $this->ses["acquis_col"] = $col;
        } elseif ($col === "Note") {
          $this->ses["note_col"] = $col;
        } elseif ($col === "Note Retenue") {
          $this->ses["note_col"] = $col;
          # ne pas mettre les colonnes résultat, puisque la seule information
          # disponible est l'absence, et qu'on n'est pas capable de calculer
          # de façon fiable les colonnes admis & ajournés
          #$this->ses["res_col"] = $col;
        } elseif ($col === "Résultat") {
          $this->ses["res_col"] = $col;
        } elseif ($col === "ECTS") {
          $this->ses["ects_col"] = $col;
        } elseif ($col === "ECTS Finaux") {
          $this->ses["ects_col"] = $col;
        } elseif ($col === "Points Jury") {
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
            if ($this->xobj >= count($this->gpt["objs"])) {
              $this->xobj = 0;
              $this->xgpt++;
              if ($this->xgpt >= count($this->data["gpts"])) {
                $updateRefs = false;
              }
            }
          }
          if ($updateRefs) {
            $this->gpt =& $this->data["gpts"][$this->xgpt];
            $this->obj =& $this->gpt["objs"][$this->xobj];
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
    foreach ($data["gpts"] as &$gpt) {
      foreach ($gpt["objs"] as &$obj) {
        foreach ($obj["sess"] as &$ses) {
          $noteCol = $ses["note_col"];
          $resCol = $ses["res_col"];
          foreach ($ses["cols"] as $col) {
            $value = $row[$sindex];
            $isValue = $value !== null && $value !== "-";
            if (!$isValue) $row[$sindex] = $value = null;
            $ses["have_value"] = $ses["have_value"] || $isValue;
            $haveNote = $col === $noteCol && $isValue;
            $ses["have_note"] = $ses["have_note"] || $haveNote;
            $haveRes = $col === $resCol && $isValue;
            $ses["have_res"] = $ses["have_res"] || $haveRes;
            $sindex++;
          }
        }; unset($ses);
      }; unset($obj);
    }; unset($gpt);
    $data["rows"][$codApr] = $row;
  }

  static function update_metadata(array &$data): void {
    $sesCols =& $data["ses_cols"];
    foreach ($data["gpts"] as &$gpt) {
      foreach ($gpt["objs"] as &$obj) {
        foreach ($obj["sess"] as $ises => &$ses) {
          $sesTitle = $ses["title"];
          $ses["is_session"] = $sesTitle !== null && (
              str::starts_with("Session ", $sesTitle) ||
              $sesTitle === "Evaluations Finales"
            );
          $ses["is_acquis"] = $sesTitle === null && $ses["acquis_col"] !== null;
          if (!isset($sesCols[$ises]["cols"])) {
            A::merge($sesCols[$ises],
              cl::select($ses, [
                "have_value", "have_note", "have_res",
                "is_acquis",
                "acquis_col",
                "is_session",
                "note_col", "res_col", "ects_col", "pj_col",
                "cols", "col_indexes",
              ]));
          }
        }; unset($ses);
      }; unset($obj);
    }; unset($gpt);
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
      "gpts" => null,
      "gpt_objs" => null,
      "ses_cols" => null,
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

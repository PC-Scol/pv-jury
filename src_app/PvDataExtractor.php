<?php
namespace app;

use nur\sery\A;
use nur\sery\cl;
use nur\sery\ext\spreadsheet\SsReader;
use nur\sery\str;
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
    $data["document"]["title"][] = implode(" ", array_filter($row));
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
          "have_value" => false,
          "have_note" => false,
          "have_res" => false,
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
        $this->data["tmp"]["gpts"][] = $this->gpt;
        $this->data["tmp"]["gpt_objs"][] = [
          "title" => $this->gpt["title"],
          "objs" => [],
        ];
        $this->newGpt = true;
        $this->gpt = null;
      }
    };
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
        $this->gpt =& $this->data["tmp"]["gpts"][$this->igpt];
        $this->gptsObjs =& $this->data["tmp"]["gpt_objs"][$this->igpt];
      }

      function new($col): bool {
        if (!$this->newObj) return false;
        $this->obj = [
          "title" => $col,
          "size" => 1,
          "have_value" => false,
          "have_note" => false,
          "have_res" => false,
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
          $this->gpt =& $this->data["tmp"]["gpts"][$igpt];
          $this->gptsObjs =& $this->data["tmp"]["gpt_objs"][$igpt];
          $this->wobj = 0;
        }
      }
    };
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
        $this->gpt =& $this->data["tmp"]["gpts"][$this->igpt];
        $this->iobj = 0;
        $this->obj =& $this->gpt["objs"][$this->iobj];
      }

      function new($col): bool {
        if (!$this->newSes) return false;
        $this->ses = [
          "title" => $col,
          "size" => 1,
          "is_session" => false,
          "have_value" => false,
          "have_note" => false,
          "have_res" => false,
          "note_col" => null,
          "res_col" => null,
          "ects_col" => null,
          "pj_col" => null,
          "bareme_col" => null,
          "coef_col" => null,
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
        $sesCols =& $this->data["tmp"]["ses_cols"];
        if (!isset($sesCols[$ises])) {
          $sesCols[$ises] = [
            "title" => $this->ses["title"],
          ];
        }
        $this->newSes = true;
        $this->ses = null;
        if ($next) {
          if ($this->wobj >= $this->gpt["size"]) {
            $this->gpt =& $this->data["tmp"]["gpts"][++$this->igpt];
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
        $this->gpt =& $this->data["tmp"]["gpts"][$this->xgpt];
        $this->obj =& $this->gpt["objs"][$this->xobj];
        $this->ses =& $this->obj["sess"][$this->xses];
      }

      function addCol($col, int $colIndex): void {
        if ($col === "Note") {
          $this->ses["note_col"] = $col;
        } elseif ($col === "Résultat") {
          $this->ses["res_col"] = $col;
        } elseif ($col === "Note Retenue") {
          $this->ses["note_col"] = $col;
          # ne pas mettre les colonnes résultat, puisque la seule information
          # disponible est l'absence, et qu'on n'est pas capable de calculer
          # de façon fiable les colonnes admis & ajournés
          #$this->ses["res_col"] = $col;
        } elseif ($col === "ECTS") {
          $this->ses["ects_col"] = $col;
        } elseif ($col === "ECTS Finaux") {
          $this->ses["ects_col"] = $col;
        } elseif ($col === "Points Jury") {
          $this->ses["pj_col"] = $col;
        } elseif ($col === "Barème") {
          $this->ses["bareme_col"] = $col;
        } elseif ($col === "Coefficient") {
          $this->ses["coef_col"] = $col;
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
              if ($this->xgpt >= count($this->data["tmp"]["gpts"])) {
                $updateRefs = false;
              }
            }
          }
          if ($updateRefs) {
            $this->gpt =& $this->data["tmp"]["gpts"][$this->xgpt];
            $this->obj =& $this->gpt["objs"][$this->xobj];
            $this->ses =& $this->obj["sess"][$this->xses];
          }
        }
      }
    };

    array_splice($row, 0, 3);
    $sindex = 3;
    foreach ($row as $col) {
      $c->addCol($col, $sindex++);
    }
    return true;
  }

  static function parse6_row(array $row, array &$data): void {
    $codApr = $row[0];
    $data["tmp"]["rows"][$codApr] = $row;
    $sindex = 3;
    foreach ($data["tmp"]["gpts"] as &$gpt) {
      foreach ($gpt["objs"] as &$obj) {
        foreach ($obj["sess"] as &$ses) {
          $noteCol = $ses["note_col"];
          $resCol = $ses["res_col"];
          foreach ($ses["cols"] as $col) {
            $value = $row[$sindex++];
            $isValue = $value !== null && $value !== "-";
            $ses["have_value"] = $ses["have_value"] || $isValue;
            $haveNote = $col === $noteCol && $isValue;
            $ses["have_note"] = $ses["have_note"] || $haveNote;
            $haveRes = $col === $resCol && $isValue;
            $ses["have_res"] = $ses["have_res"] || $haveRes;
          }
        }; unset($ses);
      }; unset($obj);
    }; unset($gpt);
  }

  static function update_metadata(array &$data): void {
    $sesCols =& $data["tmp"]["ses_cols"];
    foreach ($data["tmp"]["gpts"] as &$gpt) {
      foreach ($gpt["objs"] as &$obj) {
        foreach ($obj["sess"] as $ises => &$ses) {
          $obj["have_value"] = $obj["have_value"] || $ses["have_value"];
          $obj["have_note"] = $obj["have_note"] || $ses["have_note"];
          $obj["have_res"] = $obj["have_res"] || $ses["have_res"];
          $sesTitle = $ses["title"];
          $ses["is_session"] = $sesTitle !== null && (
              str::starts_with("Session ", $sesTitle) ||
              $sesTitle === "Evaluations Finales"
            );
          if (!isset($sesCols[$ises]["cols"])) {
            A::merge($sesCols[$ises],
              cl::select($ses, [
                "is_session",
                "have_value", "have_note", "have_res",
                "note_col", "res_col", "ects_col",
                "pj_col", "bareme_col", "coef_col",
                "cols", "col_indexes",
              ]));
          }
        }; unset($ses);
        $gpt["have_value"] = $gpt["have_value"] || $obj["have_value"];
        $gpt["have_note"] = $gpt["have_note"] || $obj["have_note"];
        $gpt["have_res"] = $gpt["have_res"] || $obj["have_res"];
      }; unset($obj);
    }; unset($gpt);
  }

  function extract($input): array {
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
      "config" => null,
      "document" => null,
      "promo" => null,
      "stats" => null,
      "totals" => null,
      "tmp" => [
        "gpts" => null,
        "gpt_objs" => null,
        "ses_cols" => null,
        "rows" => null,
      ],
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

    return $data;
  }
}

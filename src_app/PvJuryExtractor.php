<?php
namespace app;

use nur\sery\A;
use nur\sery\cl;
use nur\sery\cv;
use nur\sery\ext\spreadsheet\SsReader;
use nur\sery\str;
use stdClass;

/**
 * Class PvJuryExtractor: extraire les données d'un fichier "PV de Jury" édité
 * depuis PEGASE
 */
class PvJuryExtractor {
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
          "note_col" => null,
          "res_col" => null,
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

      function addCol($col): void {
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
        }
        $cols =& $this->ses["cols"];
        $cols[] = $col;

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
    foreach ($row as $col) {
      $c->addCol($col);
    }
    $sesCols =& $data["tmp"]["ses_cols"];
    foreach ($data["tmp"]["gpts"] as $gpt) {
      foreach ($gpt["objs"] as $obj) {
        foreach ($obj["sess"] as $ises => $ses) {
          if (!isset($sesCols[$ises]["cols"])) {
            A::merge($sesCols[$ises],
              cl::select($ses, ["note_col", "res_col", "cols"]));
          }
        }
      }
    }
    return true;
  }

  static function prepare_promo_layout(array &$data): void {
    $firstObj = true;
    $haveGpt = false;
    $stats = [];
    $colIndexes = [];
    foreach ($data["tmp"]["gpts"] as $igpt => $gpt) {
      if ($gpt["title"] !== null) $haveGpt = true;
      $stats[$igpt] = [];
      $stgpt =& $stats[$igpt];
      foreach ($gpt["objs"] as $iobj => $obj) {
        if ($firstObj) {
          $data["document"]["header"] = $obj["title"];
          $firstObj = false;
        }
        $stgpt[$iobj] = [];
        $stses =& $stgpt[$iobj];
        foreach ($obj["sess"] as $ises => $ses) {
          $sesTitle = $ses["title"];
          $note_data = null;
          $res_data = null;
          if ($ses["note_col"] !== null) {
            $note_data = [
              "notes" => [],
            ];
          }
          if ($ses["res_col"] !== null) {
            $res_data = [
              "resultats" => [],
            ];
          }
          $stses[$ises] = cl::merge($note_data, $res_data);
          foreach ($ses["cols"] as $col) {
            $colIndexes[$sesTitle][$col] = null;
          }
        }
      }
    }
    $data["config"]["have_gpt"] = $haveGpt;
    $data["tmp"]["stats"] = $stats;

    $offset = 0;
    foreach ($colIndexes as &$indexes) {
      foreach ($indexes as &$index) {
        $index = $offset++;
      }; unset($index);
    }; unset($indexes);
    $data["tmp"]["col_indexes"] = $colIndexes;

    $colRow = ["Apprenant", "Objet maquette"];
    if ($haveGpt) array_splice($colRow, 1, 0, ["Groupement"]);
    $sesRow = array_fill(0, count($colRow), null);
    foreach ($colIndexes as $sesTitle => $indexes) {
      $cols = array_keys($indexes);
      A::merge($colRow, $cols);
      $sesRow[] = cv::vn($sesTitle);
      A::merge($sesRow, array_fill(0, count($cols) - 1, null));
    }
    $data["promo"]["headers"] = [$sesRow, $colRow];
  }

  static function parse6_promo_row(array $row, array &$data): bool {
    $codApr = $row[0];
    $resultats =& $data["tmp"]["resultats"];

    $haveGpt = $data["config"]["have_gpt"];
    $colIndexes = $data["tmp"]["col_indexes"];
    $bodyPrefix = [implode(" ", array_splice($row, 0, 3))];

    # mettre le nom d'étudiant sur une ligne à part
    $data["promo"]["body"][] = $bodyPrefix; $bodyPrefix[0] = null;
    $stats =& $data["tmp"]["stats"];

    $sindex = 0;
    $firstObj = true;
    $resultat = null;
    foreach ($data["tmp"]["gpts"] as $igpt => $gpt) {
      $gptPrefix = [];
      if ($haveGpt) {
        $gptPrefix[] = null;
        $gptTitle = $gpt["title"];
        if ($gptTitle !== null) $gptPrefix[0] = $gptTitle;
      }
      foreach ($gpt["objs"] as $iobj => $obj) {
        $body = cl::merge($bodyPrefix, $gptPrefix, [$obj["title"]]);
        $dindex = count($body);
        $computeResultat = $firstObj;
        foreach ($obj["sess"] as $ises => $ses) {
          $sesTitle = $ses["title"];
          $noteCol = $ses["note_col"];
          $resCol = $ses["res_col"];
          A::merge($body, array_fill(0, $ses["size"], null));
          foreach ($ses["cols"] as $col) {
            $colIndex = $colIndexes[$sesTitle][$col];
            $value = $row[$sindex++];
            if ($col === $noteCol && is_numeric($value)) {
              $stats[$igpt][$iobj][$ises]["notes"][] = $value;
              $value = bcnumber::with($value)->floatval(3);
            } elseif ($col === $resCol && !is_numeric($value) && $value !== "-") {
              if ($computeResultat && str::starts_with("Session", $sesTitle)) {
                if (preg_match("/admis/i", $value)) {
                  $resultat = $value;
                  $computeResultat = false;
                } else {
                  $resultat = $value;
                }
              }
              $stats[$igpt][$iobj][$ises]["resultats"][] = $value;
            } elseif (is_numeric($value)) {
              $value = bcnumber::with($value)->numval(3);
            }
            $body[$dindex + $colIndex] = $value;
          }
        }
        $data["promo"]["body"][] = $body;
        $bodyPrefix[0] = null;
        if ($haveGpt) $gptPrefix[0] = null;
        $firstObj = false;
        $resultats[$codApr] = $resultat;
      }
    }
    return true;
  }

  const NOTE_COLS = ["Min", "Max", "Moy", "Écart type", "Moy-EcTyp"];
  const RES_COLS = ["Admis", "Ajournés", "Absents"];

  static function compute_stats(array &$data): void {
    $haveGpt = $data["config"]["have_gpt"];
    $stats = $data["tmp"]["stats"];

    # tout d'abord, calculer les stats
    foreach ($stats as &$gpt) {
      foreach ($gpt as &$obj) {
        foreach ($obj as &$ses) {
          if ($ses === null) continue;
          $notes = $ses["notes"] ?? null;
          if ($notes) {
            [$min, $max, $moy] = bcnumber::min_max_avg($notes);
            $stdev = bcnumber::stdev($notes);
            $moy_stdev = $moy->sub($stdev);
            $ses["note_cols"] = [
              "note_min" => $min->floatval(3),
              "note_max" => $max->floatval(3),
              "note_moy" => $moy->floatval(3),
              "stdev" => $stdev->floatval(3),
              "moy_stdev" => $moy_stdev->floatval(3),
            ];
          }
          $resultats = $ses["resultats"] ?? null;
          if ($resultats) {
            $nbAdmis = null;
            $nbAjournes = null;
            $nbAbsents = null;
            foreach ($resultats as $resultat) {
              if (preg_match('/admis/i', $resultat)) {
                $nbAdmis ??= 0;
                $nbAdmis++;
              } elseif (preg_match('/ajourne/i', $resultat)) {
                $nbAjournes ??= 0;
                $nbAjournes++;
              } elseif (preg_match('/abs/i', $resultat)) {
                $nbAbsents ??= 0;
                $nbAbsents++;
              } elseif (preg_match('/defaillant/i', $resultat)) {
                $nbAbsents ??= 0;
                $nbAbsents++;
              }
            }
            $ses["res_cols"] = [
              "nb_admis" => $nbAdmis,
              "nb_ajournes" => $nbAjournes,
              "nb_absents" => $nbAbsents,
            ];
          }
        }; unset($ses);
      }; unset($obj);
    }; unset($gpt);

    # puis calculer les en-têtes
    $sesRow = [null];
    $col1Row = [null];
    $col2Row = ["Objet maquette"];
    if ($haveGpt) {
      array_splice($sesRow, 0, 0, [null]);
      array_splice($col1Row, 0, 0, [null]);
      array_splice($col2Row, 0, 0, ["Groupement"]);
    }
    foreach ($data["tmp"]["ses_cols"] as $ses) {
      $haveNoteCol = $ses["note_col"] !== null;
      $haveResCol = $ses["res_col"] !== null;
      if (!$haveNoteCol && !$haveResCol) continue;
      $cols = [];
      if ($haveNoteCol) {
        A::merge($col1Row,
          ["Notes"],
          array_fill(0, count(self::NOTE_COLS) - 1, null));
        A::merge($cols, self::NOTE_COLS);
      }
      if ($haveResCol) {
        A::merge($col1Row,
          ["Résultats"],
          array_fill(0, count(self::RES_COLS) - 1, null));
        A::merge($cols, self::RES_COLS);
      }
      A::merge($sesRow,
        [$ses["title"]],
        array_fill(0, count($cols) - 1, null));
      A::merge($col2Row, $cols);
    }
    $data["stats"]["headers"] = [$sesRow, $col1Row, $col2Row];

    # puis faire autant de lignes que nécessaire
    $body =& $data["stats"]["body"];
    foreach ($data["tmp"]["gpts"] as $igpt => $gpt) {
      $gptTitle = $gpt["title"];
      foreach ($gpt["objs"] as $iobj => $obj) {
        $row = $haveGpt? [$gptTitle]: [];
        $row[] = $obj["title"];
        foreach ($obj["sess"] as $ises => $ses) {
          $haveNoteCol = $ses["note_col"] !== null;
          $haveResCol = $ses["res_col"] !== null;
          if (!$haveNoteCol && !$haveResCol) continue;
          if ($haveNoteCol) {
            $noteCols = $stats[$igpt][$iobj][$ises]["note_cols"] ?? null;
            if ($noteCols !== null) $noteCols = array_values($noteCols);
            else $noteCols = array_fill(0, count(self::NOTE_COLS), null);
            A::merge($row, $noteCols);
          }
          if ($haveResCol) {
            $resCols = $stats[$igpt][$iobj][$ises]["res_cols"] ?? null;
            if ($resCols !== null) $resCols = array_values($resCols);
            else $resCols = array_fill(0, count(self::RES_COLS), null);
            A::merge($row, $resCols);
          }
        }
        $body[] = $row;
        $gptTitle = null;
      }
    }

    $resultats = $data["tmp"]["resultats"];
    $nbAdmis = 0;
    $nbAjournes = 0;
    $nbAbsents = 0;
    foreach ($resultats as $resultat) {
      if (preg_match('/admis/i', $resultat)) {
        $nbAdmis++;
      } elseif (preg_match('/ajourne/i', $resultat)) {
        $nbAjournes++;
      } elseif (preg_match('/abs/i', $resultat)) {
        $nbAbsents++;
      } elseif (preg_match('/defaillant/i', $resultat)) {
        $nbAbsents++;
      }
    }
    $nbApprenants = count($resultats);
    $totals = [
      "nb_apprenants" => $nbApprenants,
      "nb_admis" => $nbAdmis,
      "per_admis" => bcnumber::with($nbAdmis)->_div($nbApprenants)->floatval(4),
      "nb_ajournes" => $nbAjournes,
      "per_ajournes" => bcnumber::with($nbAjournes)->_div($nbApprenants)->floatval(4),
      "nb_absents" => $nbAbsents,
    ];
    $data["totals"]["headers"] = [
      [null, "Nombre", "Pourcentage"],
    ];
    $data["totals"]["body"] = [
      ["Etudiants", $totals["nb_apprenants"], null],
      ["Admis", $totals["nb_admis"], $totals["per_admis"]],
      ["Ajournés", $totals["nb_ajournes"], $totals["per_ajournes"]],
      ["Absents", $totals["nb_absents"], null],
    ];
  }

  static function cleanup(array &$data): void {
    unset($data["tmp"]);
  }

  function extract($input): array {
    $reader = SsReader::with($input, [
      "use_headers" => false,
      "parse_not" => true,
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
      "tmp" => null,
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
        self::prepare_promo_layout($data);
        $state = 6;
      } elseif ($state == 6) {
        self::parse6_promo_row($row, $data);
      }
    }
    self::compute_stats($data);
    self::cleanup($data);

    return $data;
  }
}

<?php
namespace app;

use nur\sery\A;
use nur\sery\cl;
use nur\sery\cv;
use nur\sery\str;

class CsvPvModel2Builder extends CsvPvBuilder {
  static function prepare_layout(array &$data): void {
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

  static function parse_row(array $row, array &$data): bool {
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

  protected function compute(array &$data): void {
    self::prepare_layout($data);
    foreach ($data["tmp"]["rows"] as $row) {
      self::parse_row($row, $data);
    }
    self::compute_stats($data);
  }

  protected function writeRows(array $data): void {
    $builder = $this->builder;

    foreach ($data["document"]["title"] as $line) {
      $builder->write([$line]);
    }

    $builder->write([]);
    foreach ($data["promo"]["headers"] as $row) {
      $builder->write($row);
    }
    foreach ($data["promo"]["body"] as $row) {
      $builder->write($row);
    }

    $builder->write([]);
    $prefix = [null];
    foreach ($data["stats"]["headers"] as $row) {
      $builder->write(cl::merge($prefix, $row));
    }
    foreach ($data["stats"]["body"] as $row) {
      $builder->write(cl::merge($prefix, $row));
    }

    $builder->write([]);
    $prefix = [null];
    if ($data["config"]["have_gpt"]) $prefix[] = null;
    foreach ($data["totals"]["headers"] as $row) {
      $builder->write(cl::merge($prefix, $row));
    }
    foreach ($data["totals"]["body"] as $row) {
      $builder->write(cl::merge($prefix, $row));
    }
  }
}

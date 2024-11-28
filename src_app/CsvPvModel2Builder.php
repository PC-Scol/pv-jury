<?php
namespace app;

use nulib\A;
use nulib\cl;
use nulib\cv;
use nulib\str;
use nur\v\vo;

class CsvPvModel2Builder extends CsvPvBuilder {
  protected function verifixPvData(PVData $pvData): void {
    $pvData->ws = [
      "document" => null,
      "sheet_promo" => null,
      "sheet_stats" => null,
      "sheet_totals" => null,
    ];
  }

  static function prepare_layout(PvData $pvData): void {
    $data = $pvData->data;
    $ws =& $pvData->ws;
    $promo =& $ws["sheet_promo"];
    $stats =& $ws["stats"];

    $ws["document"]["title"] = $data["title"];
    $firstObj = true;
    $haveGpt = false;
    $stats = [];
    $colIndexes = [];
    foreach ($data["gpts"] as $igpt => $gpt) {
      if ($gpt["title"] !== null) $haveGpt = true;
      $stats[$igpt] = [];
      $stgpt =& $stats[$igpt];
      foreach ($gpt["objs"] as $iobj => $obj) {
        if ($firstObj) {
          $ws["document"]["header"] = $obj["title"];
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
    $ws["have_gpt"] = $haveGpt;

    $offset = 0;
    foreach ($colIndexes as &$indexes) {
      foreach ($indexes as &$index) {
        $index = $offset++;
      }; unset($index);
    }; unset($indexes);
    $ws["col_indexes"] = $colIndexes;

    $colRow = ["Apprenant", "Objet maquette"];
    if ($haveGpt) array_splice($colRow, 1, 0, ["Groupement"]);
    $sesRow = array_fill(0, count($colRow), null);
    foreach ($colIndexes as $sesTitle => $indexes) {
      $cols = array_keys($indexes);
      A::merge($colRow, $cols);
      $sesRow[] = cv::vn($sesTitle);
      A::merge($sesRow, array_fill(0, count($cols) - 1, null));
    }
    $promo["headers"] = [$sesRow, $colRow];
  }

  static function parse_row(array $row, PvData $pvData): bool {
    $codApr = $row[0];
    $data = $pvData->data;
    $ws =& $pvData->ws;
    $promo =& $ws["sheet_promo"];
    $stats =& $ws["stats"];
    $resultats =& $ws["resultats"];

    $haveGpt = $ws["have_gpt"];
    $colIndexes = $ws["col_indexes"];
    $bodyPrefix = [implode(" ", array_splice($row, 0, 3))];

    # mettre le nom d'étudiant sur une ligne à part
    $promo["body"][] = $bodyPrefix; $bodyPrefix[0] = null;

    $sindex = 0;
    $firstObj = true;
    $resultat = null;
    foreach ($data["gpts"] as $igpt => $gpt) {
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
        $promo["body"][] = $body;
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

  static function compute_stats(PvData $pvData): void {
    $data = $pvData->data;
    $ws =& $pvData->ws;

    $haveGpt = $ws["have_gpt"];

    # tout d'abord, calculer les stats
    foreach ($ws["stats"] as &$gpt) {
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
    foreach ($data["ses_cols"] as $ses) {
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
    $ws["sheet_stats"]["headers"] = [$sesRow, $col1Row, $col2Row];

    # puis faire autant de lignes que nécessaire
    $body =& $ws["sheet_stats"]["body"];
    foreach ($data["gpts"] as $igpt => $gpt) {
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

    $resultats = $ws["resultats"];
    $nbApprenants = count($resultats);
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
    $totals = [
      "nb_apprenants" => $nbApprenants,
      "nb_admis" => $nbAdmis,
      "per_admis" => bcnumber::with($nbAdmis)->_div($nbApprenants)->floatval(4),
      "nb_ajournes" => $nbAjournes,
      "per_ajournes" => bcnumber::with($nbAjournes)->_div($nbApprenants)->floatval(4),
      "nb_absents" => $nbAbsents,
    ];
    $ws["sheet_totals"]["headers"] = [
      [null, "Nombre", "Pourcentage"],
    ];
    $ws["sheet_totals"]["body"] = [
      ["Etudiants", $totals["nb_apprenants"], null],
      ["Admis", $totals["nb_admis"], $totals["per_admis"]],
      ["Ajournés", $totals["nb_ajournes"], $totals["per_ajournes"]],
      ["Absents", $totals["nb_absents"], null],
    ];
  }

  function setCodApr(string $codApr) {
    $this->codApr = $codApr;
  }

  private ?string $codApr = null;

  function compute(?PvData $pvData=null): static {
    $this->ensurePvData($pvData);

    $pvData->ws["sheet_promo"] = null;
    $pvData->ws["sheet_stats"] = null;
    $pvData->ws["sheet_totals"] = null;
    self::prepare_layout($pvData);

    $codApr = $this->codApr;
    foreach ($pvData->rows as $row) {
      if ($codApr !== null && $row[0] !== $codApr) continue;
      self::parse_row($row, $pvData);
    }

    self::compute_stats($pvData);
    return $this;
  }

  protected function writeRows(?PvData $pvData=null): void {
    $this->ensurePvData($pvData);
    $builder = $this->builder;
    $ws = $pvData->ws;

    foreach ($ws["document"]["title"] as $line) {
      $builder->write([$line]);
    }

    $promo = $ws["sheet_promo"];
    $builder->write([]);
    foreach ($promo["headers"] as $row) {
      $builder->write($row);
    }
    foreach ($promo["body"] as $row) {
      $builder->write($row);
    }

    $stats = $ws["sheet_stats"];
    $builder->write([]);
    $prefix = [null];
    foreach ($stats["headers"] as $row) {
      $builder->write(cl::merge($prefix, $row));
    }
    foreach ($stats["body"] as $row) {
      $builder->write(cl::merge($prefix, $row));
    }

    $totals = $ws["sheet_totals"];
    $builder->write([]);
    $prefix = [null];
    if ($ws["have_gpt"]) $prefix[] = null;
    foreach ($totals["headers"] as $row) {
      $builder->write(cl::merge($prefix, $row));
    }
    foreach ($totals["body"] as $row) {
      $builder->write(cl::merge($prefix, $row));
    }
  }

  function print(): void {
    $ws = $this->pvData->ws;
    $promo = $ws["sheet_promo"];
    $one = $this->codApr !== null;

    if ($one) vo::h1($promo["body"][0]);

    vo::stable(["class" => "table table-bordered"]);
    vo::sthead();
    foreach ($promo["headers"] as $row) {
      vo::str();
      $first = true;
      foreach ($row as $col) {
        if ($one && $first) {
          $first = false;
          continue;
        }
        vo::th($col);
      }
      vo::etr();
    }
    vo::ethead();
    vo::stbody();
    foreach ($promo["body"] as $row) {
      vo::str();
      $first = true;
      foreach ($row as $col) {
        if ($one && $first) {
          $first = false;
          continue;
        }
        vo::td($col);
      }
      vo::etr();
    }
    vo::etbody();
    vo::etable();
  }
}

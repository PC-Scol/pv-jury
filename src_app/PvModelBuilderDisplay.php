<?php
namespace app;

use nulib\A;
use nulib\cl;
use nulib\cv;
use nur\b\values\Breaker;
use nur\v\vo;

/**
 * Class PvModelBuilderDisplay: construire un document pouvant servir à
 * la consultation individuelle des notes et résultats pour un étudiant:
 * affichage exhaustif et en lignes
 */
class PvModelBuilderDisplay extends PvModelBuilder {
  static function prepare_layout(PvData $pvData): void {
    $data = $pvData->data;
    $ws =& $pvData->ws;
    $promo =& $ws["sheet_promo"];

    $ws["document"]["title"] = $data["title"];
    $ws["document"]["header"] = cl::first($data["objs"])["title"];

    $colRow = ["Apprenant", "Objet maquette"];
    if ($data["have_gpts"]) {
      $colRow[] = "Groupements";
      $colRow[] = "Objets fils";
    }
    $sesRow = array_fill(0, count($colRow), null);
    $colIndexes = [];
    $index = 0;
    foreach ($data["ses_cols"] as $ses) {
      $sesTitle = $ses["title"];
      $cols = $ses["cols"];
      foreach ($cols as $col) {
        $colIndexes[$sesTitle][$col] = $index++;
      }
      A::merge($colRow, $cols);
      $sesRow[] = cv::vn($sesTitle);
      A::merge($sesRow, array_fill(0, count($cols) - 1, null));
    }
    $promo["headers"] = [$sesRow, $colRow];
    $ws["col_indexes"] = $colIndexes;
  }

  static function parse_row(array $row, PvData $pvData): bool {
    $codApr = $row[0];
    $data = $pvData->data;
    $haveGpts = $data["have_gpts"];
    $ws =& $pvData->ws;
    $promo =& $ws["sheet_promo"];

    $colIndexes = $ws["col_indexes"];
    $bodyPrefix = [implode(" ", array_splice($row, 0, 3))];

    # mettre le nom d'étudiant sur une ligne à part
    $promo["body"][] = $bodyPrefix; $bodyPrefix[0] = null;

    $sindex = 0;
    $breaker = new Breaker();
    foreach ($data["objs"] as $iobj => $obj) {
      if ($haveGpts) {
        $gptTitle = $obj["gpt_title"];
        if ($obj["gpt_parent"]) {
          # parent
          $gptTitle = null;
          $prefix = [$obj["title"], null, null];
        } elseif ($gptTitle !== null) {
          # enfant
          if ($breaker->shouldBreakOn($gptTitle)) {
            $promo["body"][] = cl::merge($bodyPrefix, [null, $gptTitle, null]);
          }
          $prefix = [null, null, $obj["title"]];
        } else {
          $prefix = [$obj["title"], null, null];
        }
      } else {
        $prefix = [$obj["title"]];
      }
      $body = cl::merge($bodyPrefix, $prefix);

      $dindex = count($body);
      foreach ($obj["sess"] as $ises => $ses) {
        $sesTitle = $ses["title"];
        $sesSize = count($colIndexes[$sesTitle]);
        $noteCol = $ses["note_col"];
        $resCol = $ses["res_col"];
        A::merge($body, array_fill(0, $sesSize, null));
        foreach ($ses["cols"] as $col) {
          $colIndex = $colIndexes[$sesTitle][$col];
          $value = $row[$sindex++];
          if ($col === $noteCol && is_numeric($value)) {
            $value = bcnumber::with($value)->floatval(3);
          } elseif ($col === $resCol && !is_numeric($value) && $value !== "-") {
          } elseif (is_numeric($value)) {
            $value = bcnumber::with($value)->numval(3);
          }
          $body[$dindex + $colIndex] = $value;
        }
      }
      $promo["body"][] = $body;
      $bodyPrefix[0] = null;
    }
    return true;
  }

  function setCodApr(string $codApr) {
    $this->codApr = $codApr;
  }

  private ?string $codApr = null;

  function compute(): static {
    $pvData = $this->pvData;

    $pvData->ws = [
      "document" => null,
      "sheet_promo" => null,
    ];
    self::prepare_layout($pvData);

    $codApr = $this->codApr;
    foreach ($pvData->rows as $row) {
      if ($codApr !== null && $row[0] !== $codApr) continue;
      self::parse_row($row, $pvData);
    }

    return $this;
  }

  protected function writeRows(): void {
    $pvData = $this->pvData;
    $data = $pvData->data;
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
    if ($data["have_gpts"]) $prefix[] = null;
    foreach ($totals["headers"] as $row) {
      $builder->write(cl::merge($prefix, $row));
    }
    foreach ($totals["body"] as $row) {
      $builder->write(cl::merge($prefix, $row));
    }
  }

  #############################################################################

  function checkForm(): bool {
    return true;
  }

  function printForm(): void {
  }

  function doFormAction(?array $params=null): void {
  }

  #############################################################################

  function print(?array $params=null): void {
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

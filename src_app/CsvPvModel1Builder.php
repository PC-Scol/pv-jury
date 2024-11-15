<?php
namespace app;

use nur\sery\cl;
use nur\sery\ValueException;

/**
 * Class Model1CsvBuilder: construire un document pouvant servir à faire une
 * délibération: sélection par session, et affichage en colonnes
 */
class CsvPvModel1Builder extends CsvPvBuilder {
  function getSessions(): array {
    $sessions = [];
    foreach ($this->pvData->sesCols as $ises => $ses) {
      if ($ses["is_session"] && $ses["have_value"]) {
        $sessions[$ises] = [$ises, $ses["title"]];
      }
    }
    return $sessions;
  }

  private int $ises;

  private array $ses;

  function setIses(int $ises) {
    $ses = $this->pvData->sesCols[$ises] ?? null;
    $this->ses = ValueException::check_null($ses);
    $this->ises = $ises;
  }

  const RES_MAP = [
    "ADMIS" => "ADM /",
    "ADMIS PAR COMPENSATION" => "ADMC /",
    "AJOURNE" => "AJ",
  ];

  function prepareMetadata(): void {
    $data = $this->pvData->data;
    $ws =& $this->pvData->ws;

    $ws["document"]["title"] = $data["title"];
    $firstObj = true;
    $haveGpt = false;
    $objs = [];
    foreach ($data["gpts"] as $gpt) {
      if (!$gpt["have_value"]) continue;
      if ($gpt["title"] !== null) $haveGpt = true;
      foreach ($gpt["objs"] as $obj) {
        if (!$obj["have_value"]) continue;
        if ($firstObj) {
          $ws["document"]["header"] = $obj["title"];
          $firstObj = false;
        }
        foreach ($obj["sess"] as $ises => $ses) {
          if (!$ses["have_value"]) continue;
          if ($ises === $this->ises) {
            unset($obj["sess"]);
            $obj["ses"] = $ses;
            break;
          }
        }
        $objs[] = $obj;
      }
    }
    $ws["have_gpt"] = $haveGpt;
    $ws["objs"] = $objs;
  }

  function prepareLayout(): void {
    $ws =& $this->pvData->ws;
    $objs = $ws["objs"];
    $pv =& $ws["sheet_pv"];

    $hrow1 = [null, "Code apprenant", "Nom", "Prénom"];
    $hrow2 = [null, null, null, null];
    $firstObj = true;
    foreach ($objs as $obj) {
      $title = $obj["title"];
      $ects = null;
      if ($firstObj) {
        $firstObj = false;
        $code = "Note";
        $title = "Résultat";
        $ects = "ECTS";
      } elseif (preg_match('/^(.*?) - (.*)/', $title, $ms)) {
        $code = $ms[1];
        $title = $ms[2];
      } else {
        $code = $title;
        $title = null;
      }
      $hrow1[] = $code;
      $hrow1[] = null;
      $hrow2[] = $title;
      $hrow2[] = $ects;
    }
    $pv["headers"] = [$hrow1, $hrow2];
  }

  function parseRow(array $row): void {
    $ws =& $this->pvData->ws;
    $objs = $ws["objs"];
    $pv =& $ws["sheet_pv"];

    $brow1 = cl::merge([null], array_slice($row, 0, 3));
    $brow2 = [null, null, null, null];
    foreach ($objs as $obj) {
      $ses = $obj["ses"];
      $noteCol = $ses["note_col"];
      $resCol = $ses["res_col"];
      $ectsCol = $ses["ects_col"];
      $note = null;
      if ($noteCol !== null) {
        $note = $row[$ses["col_indexes"][$noteCol]];
        if (is_numeric($note)) $note = bcnumber::with($note)->floatval(3);
      }
      $brow1[] = $note;
      $brow1[] = null;
      $res = null;
      if ($resCol !== null) {
        $res = $row[$ses["col_indexes"][$resCol]];
        $res = cl::get(self::RES_MAP, $res, $res);
      }
      $brow2[] = $res;
      $ects = null;
      if ($ectsCol !== null) {
        $ects = $row[$ses["col_indexes"][$ectsCol]];
        if (is_numeric($ects)) $ects = bcnumber::with($ects)->numval(3);
      }
      $brow2[] = $ects;
    }
    $pv["body"][] = $brow1;
    $pv["body"][] = $brow2;
  }

  function compute(?PvData $pvData=null): static {
    $this->ensurePvData($pvData);
    $pvData->ws = [
      "document" => null,
      "sheet_pv" => null,
    ];

    $this->prepareMetadata();
    $this->prepareLayout();
    foreach ($pvData->rows as $row) {
      $this->parseRow($row);
    }
    return $this;
  }

  protected function writeRows(?PvData $pvData=null): void {
    $this->ensurePvData($pvData);
    $builder = $this->builder;
    $ws = $pvData->ws;

    foreach ($ws["document"]["title"] as $line) {
      $builder->write([$line]);
    }

    $pv = $ws["sheet_pv"];
    $builder->write([]);
    foreach ($pv["headers"] as $row) {
      $builder->write($row);
    }
    foreach ($pv["body"] as $row) {
      $builder->write($row);
    }
  }
}

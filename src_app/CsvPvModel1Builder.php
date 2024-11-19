<?php
namespace app;

use nur\sery\cl;
use nur\sery\cv;
use nur\sery\ValueException;
use nur\v\vo;

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

  function setIses(int $ises): void {
    $ses = $this->pvData->sesCols[$ises] ?? null;
    ValueException::check_null($ses);
    $this->ises = $ises;
  }

  const ORDER_MERITE = "note", ORDER_ALPHA = "nom";

  private string $order = self::ORDER_ALPHA;

  function setOrder(string $order): void {
    $this->order = $order;
  }

  protected function getBuilderParams(): ?array {
    return [
      "wsparams" => [
        "sheetView_freezeRow" => 8,
      ],
    ];
  }

  const RES_MAP = [
    "ADMIS" => "ADM",
    "AJOURNE" => "AJ",
    "ADMIS PAR COMPENSATION" => "ADMC",
    "AJOURNE AUTORISE A CONTINUER" => "AJAC",
    "DEFAILLANT" => "DEF",
    "ELIMINE" => "ELIM",
    "EN ATTENTE" => "ATT",
    "NEUTRALISE" => "NEU",
    "ACQUIS" => "ACQUIS",
    "ABS. INJ." => "ABI",
    "ABS. JUS." => "ABS",
  ];

  private static function split_code_title(string &$title, ?string &$code=null): bool {
    if (preg_match('/^(.*?) - (.*)/', $title, $ms)) {
      $code = $ms[1];
      $title = $ms[2];
      return true;
    }
    return false;
  }

  function prepareMetadata(): void {
    $data = $this->pvData->data;
    $ws =& $this->pvData->ws;

    $haveGpt = false;
    $firstObj = true;
    $titleObj = null;
    $titleSes = null;
    $tmpObjs = [];
    # construire la liste des objets
    foreach ($data["gpts"] as $gpt) {
      if ($gpt["title"] !== null) $haveGpt = true;
      foreach ($gpt["objs"] as $obj) {
        if ($firstObj) {
          $titleObj = $obj["title"];
          self::split_code_title($titleObj);
          $firstObj = false;
        }
        $obj["acq"] = null;
        $obj["ses"] = null;
        foreach ($obj["sess"] as $ises => $ses) {
          if ($obj["acq"] === null && $ses["is_acquis"]) {
            $obj["acq"] = $ses;
          }
          if ($obj["ses"] === null && $ises === $this->ises) {
            $titleSes ??= $ses["title"];
            $obj["ses"] = $ses;
          }
        }
        unset($obj["sess"]);
        $tmpObjs[] = $obj;
      }
    }
    # puis filtrer ceux qui n'ont pas de données
    $objs = [];
    foreach ($tmpObjs as $obj) {
      if ($obj["ses"] === null) continue;
      if (!$obj["ses"]["have_value"]) continue;
      $objs[] = $obj;
    }
    $ws["have_gpt"] = $haveGpt;
    $ws["objs"] = $objs;

    $ws["document"]["title"] = [
      cl::first($data["title"]),
      $titleObj,
      $titleSes,
      cl::last($data["title"]),
    ];
  }

  function prepareLayout(): void {
    $ws =& $this->pvData->ws;
    $objs = $ws["objs"];
    $pv =& $ws["sheet_pv"];

    $hrow1 = ["Code apprenant", "Nom", "Prénom"];
    $hrow2 = [null, null, null];
    $firstObj = true;
    foreach ($objs as $obj) {
      $title = $obj["title"];
      $ects = null;
      if ($firstObj) {
        $firstObj = false;
        $code = "Note";
        $title = "Résultat";
        $ects = "ECTS";
      } elseif (!self::split_code_title($title, $code)) {
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

  function getAcqNoteResEcts(array $row, array $obj): array {
    $acq = $obj["acq"];
    $acquisCol = $acq["acquis_col"];
    $acquis = null;
    if ($acquisCol !== null) {
      $acquis = $row[$acq["col_indexes"][$acquisCol]];
      if (preg_match('/CAPITALISÉ(?: \(\d{2}(\d{2})-\d{2}(\d{2})\))?/u', $acquis, $ms)) {
        $f = $ms[1] ?? null;
        $t = $ms[2] ?? null;
        if ($f && $t) $acquis = "CAP$f-$t";
        else $acquis = "CAP";
      } elseif ($acquis === "VALIDATION - EVALUATION") {
        $acquis = "VAL-EVAL";
      } else {
        $acquis = null;
      }
    }

    $ses = $obj["ses"];
    $noteCol = $ses["note_col"];
    $note = null;
    $resCol = $ses["res_col"];
    $res = null;
    $ectsCol = $ses["ects_col"];
    $ects = null;
    if ($resCol !== null) {
      $res = $row[$ses["col_indexes"][$resCol]];
      $res = cl::get(self::RES_MAP, $res, $res);
    }
    if ($noteCol !== null) {
      $note = $row[$ses["col_indexes"][$noteCol]];
      if (is_numeric($note)) {
        $note = bcnumber::with($note)->floatval(3);
      } elseif (is_string($note)) {
        if ($res === null) $res = cl::get(self::RES_MAP, $note, $note);
        $note = null;
      }
    }
    if ($ectsCol !== null) {
      $ects = $row[$ses["col_indexes"][$ectsCol]];
      if (is_numeric($ects)) {
        $ects = bcnumber::with($ects)->numval(3);
      } elseif (is_string($ects)) {
        $ects = cl::get(self::RES_MAP, $ects, $ects);
      }
    }

    return [
      "acquis" => $acquis,
      "note" => $note,
      "res" => $res,
      "ects" => $ects,
    ];
  }

  function compareNom(array $a, array $b) {
    $comparator = cl::compare(["+1", "+2"]);
    return $comparator($a, $b);
  }

  function compareNote(array $rowa, array $rowb) {
    $obj = cl::first($this->pvData->ws["objs"]);
    ["note" => $notea,
    ] = $this->getAcqNoteResEcts($rowa, $obj);
    ["note" => $noteb,
    ] = $this->getAcqNoteResEcts($rowb, $obj);
    if (!is_numeric($notea)) $notea = -1;
    if (!is_numeric($noteb)) $noteb = -1;
    $c = -cv::compare($notea, $noteb);
    if ($c !== 0) return $c;
    else return $this->compareNom($rowa, $rowb);
  }

  function parseRow(array $row): void {
    $ws =& $this->pvData->ws;
    $objs = $ws["objs"];
    $pv =& $ws["sheet_pv"];
    $notes =& $ws["notes"];
    $resultats =& $ws["resultats"];
    $codApr = $row[0];

    $brow1 = array_slice($row, 0, 3);
    $brow2 = [null, null, null];
    $firstObj = true;
    foreach ($objs as $iobj => $obj) {
      [
        "acquis" => $acquis,
        "note" => $note,
        "res" => $res,
        "ects" => $ects,
      ] = $this->getAcqNoteResEcts($row, $obj);
      $brow1[] = $note;
      $brow1[] = $acquis;
      $brow2[] = $res;
      $brow2[] = $ects;

      if ($note !== null) {
        $notes[$iobj][$codApr] = $note;
      }
      if ($firstObj && $res !== null) {
        $resultats[$codApr] = $res;
      }
      $firstObj = false;
    }
    $pv["body"][] = $brow1;
    $pv["body"][] = $brow2;
  }

  private static function stat_brow(string $label, array $notes): array {
    $brow = [null, null, $label];
    foreach ($notes as $note) {
      /** @var bcnumber $note */
      if ($note !== null) $note = $note->floatval(3);
      $brow[] = $note;
      $brow[] = null;
    }
    return $brow;
  }

  function computeStats(): void {
    $ws =& $this->pvData->ws;
    $objs = $ws["objs"];
    $pv =& $ws["sheet_pv"];

    $stats = [
      "notes_min" => [],
      "notes_max" => [],
      "notes_avg" => [],
      "stdev" => [],
      "avg_stdev" => [],
    ];
    $notes = $ws["notes"];
    foreach ($objs as $iobj => $obj) {
      $onotes = $notes[$iobj] ?? null;
      if ($onotes !== null) {
        [$min, $max, $avg] = bcnumber::min_max_avg($onotes, true);
        $stdev = bcnumber::stdev($onotes, true);
        $avg_stdev = $avg->sub($stdev);
      } else {
        $min = $max = $avg = $stdev = $avg_stdev = null;
      }
      $stats["notes_min"][] = $min;
      $stats["notes_max"][] = $max;
      $stats["notes_avg"][] = $avg;
      $stats["stdev"][] = $stdev;
      $stats["avg_stdev"][] = $avg_stdev;
    }

    $pv["body"][] = self::stat_brow("Note min", $stats["notes_min"]);
    $pv["body"][] = self::stat_brow("Note max", $stats["notes_max"]);
    $pv["body"][] = self::stat_brow("Note moy", $stats["notes_avg"]);
    $pv["body"][] = self::stat_brow("écart-type", $stats["stdev"]);
    $pv["body"][] = self::stat_brow("moy - écart-type", $stats["avg_stdev"]);

    $resultats = $ws["resultats"];
    if ($resultats !== null) {
      $nbApprenants = count($resultats);
      $nbAdmis = 0;
      $nbAjournes = 0;
      $nbAbsents = 0;
      foreach ($resultats as $resultat) {
        if (preg_match('/^adm/i', $resultat)) {
          $nbAdmis++;
        } elseif (preg_match('/^aj/i', $resultat)) {
          $nbAjournes++;
        } elseif (preg_match('/^ab/i', $resultat)) {
          $nbAbsents++;
        } elseif (preg_match('/^def/i', $resultat)) {
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
    } else {
      $totals = [
        "nb_apprenants" => null,
        "nb_admis" => null,
        "per_admis" => null,
        "nb_ajournes" => null,
        "per_ajournes" => null,
        "nb_absents" => null,
      ];
    }

    $ws["sheet_totals"]["headers"] = [
      [null, "Nombre", "Pourcentage"],
    ];
    $ws["sheet_totals"]["body"] = [
      ["Nb étudiants", $totals["nb_apprenants"], null],
      ["Nb admis", $totals["nb_admis"], $totals["per_admis"]],
      ["Nb ajournés", $totals["nb_ajournes"], $totals["per_ajournes"]],
      ["Nb absents", $totals["nb_absents"], null],
    ];
  }

  function compute(?PvData $pvData=null): static {
    $this->ensurePvData($pvData);
    $pvData->ws = [
      "document" => null,
      "sheet_pv" => null,
    ];

    $this->prepareMetadata();
    $this->prepareLayout();
    $rows = $pvData->rows;
    switch ($this->order) {
    case self::ORDER_MERITE:
      usort($rows, [self::class, "compareNote"]);
      break;
    case self::ORDER_ALPHA:
      usort($rows, [self::class, "compareNom"]);
      break;
    }
    foreach ($rows as $row) {
      $this->parseRow($row);
    }
    $this->computeStats();
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
    $prefix = [null];
    foreach ($pv["headers"] as $row) {
      $builder->write(cl::merge($prefix, $row));
    }
    foreach ($pv["body"] as $row) {
      $builder->write(cl::merge($prefix, $row));
    }

    $totals = $ws["sheet_totals"];
    $builder->write([]);
    $prefix = [null, null, null];
    foreach ($totals["headers"] as $row) {
      $builder->write(cl::merge($prefix, $row));
    }
    foreach ($totals["body"] as $row) {
      $builder->write(cl::merge($prefix, $row));
    }

    $builder->write([]);
    $prefix = [null, null, null];
    $builder->write(cl::merge($prefix, ["Le président du jury"]));
    $builder->write(cl::merge($prefix, ["Date"]));
    $builder->write(cl::merge($prefix, ["Les membres du jury"]));
  }

  function print(): void {
    $ws = $this->pvData->ws;
    $pv = $ws["sheet_pv"];

    vo::stable(["class" => "table table-bordered"]);
    vo::sthead();
    foreach ($pv["headers"] as $row) {
      vo::str();
      foreach ($row as $col) {
        vo::th($col);
      }
      vo::etr();
    }
    vo::ethead();
    vo::stbody();
    foreach ($pv["body"] as $row) {
      vo::str();
      foreach ($row as $col) {
        vo::td($col);
      }
      vo::etr();
    }
    vo::etbody();
    vo::etable();
  }
}

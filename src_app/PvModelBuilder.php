<?php
namespace app;

use nulib\cl;
use nulib\cv;
use nulib\ext\tab\SsBuilder;
use nulib\file;
use nulib\file\tab\IBuilder;
use nulib\output\msg;
use nulib\ValueException;
use nur\v\bs3\fo\Form;

abstract class PvModelBuilder {
  const RES_MAP = [
    "ADMIS" => "ADM",
    "AJOURNE" => "AJ",
    "ADMIS PAR COMPENSATION" => "ADMC",
    "AJOURNE AUTORISE A CONTINUER" => "AJAC",
    "DEFAILLANT" => "DEF",
    "ELIMINE" => "ELIM",
    "EN ATTENTE" => "ATT",
    "NEUTRALISE" => "NEU",
    "ACQUIS" => "ACQ",
    "NON ACQUIS" => "NON_ACQ",
    "EN COURS D'ACQUISITION" => "ENC_ACQ",
    "ABS. INJ." => "ABI",
    "ABS. JUS." => "ABS",
  ];

  function __construct(PvData $pvData) {
    $this->pvData = $pvData;
  }

  protected PvData $pvData;

  function setPvData(PvData $pvData): static {
    $this->pvData = $pvData;
    return $this;
  }

  function setIses($ises): static {
    return $this;
  }

  function setIcols($icols): static {
    return $this;
  }

  const ORDER_MERITE = "note", ORDER_ALPHA = "nom", ORDER_CODAPR = "codapr";

  protected string $order = self::ORDER_MERITE;

  function setOrder(string $order): static {
    $this->order = $order;
    return $this;
  }

  function setAddCoeffCol(bool $addCoeffCol=true): static {
    return $this;
  }

  function setExcludeControles(bool $excludeControles=true): static {
    return $this;
  }

  function setExcludeUnlessHaveValue(bool $excludeUnlessHaveValue=true): static {
    return $this;
  }

  function setIncludeObjs(?array $includeObjs): static {
    return $this;
  }

  function getAcq(array $row, array $acq): array {
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
    return ["acquis" => $acquis];
  }

  function getNoteResEctsPj(array $row, array $ses): array {
    $noteCol = $ses["note_col"];
    $note = null;
    $resCol = $ses["res_col"];
    $res = null;
    $ectsCol = $ses["ects_col"];
    $ects = null;
    $pjCol = $ses["pj_col"];
    $pj = null;
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
    if ($pjCol !== null) {
      $pj = $row[$ses["col_indexes"][$pjCol]];
      if (is_numeric($pj)) {
        $pj = bcnumber::with($pj)->numval(3);
      } elseif (is_string($pj)) {
        $pj = cl::get(self::RES_MAP, $pj, $pj);
      }
    }
    return [
      "note" => $note,
      "res" => $res,
      "ects" => $ects,
      "pj" => $pj,
    ];
  }

  function getAcqNoteResEctsPjCoeff(array $row, array $ses, ?array $acq): array {
    if ($acq !== null) $acq = $this->getAcq($row, $acq);
    $noteResEctsPj = $this->getNoteResEctsPj($row, $ses);
    $coeffCol = $ses["col_indexes"]["Coefficient"] ?? null;
    return cl::merge($acq, $noteResEctsPj, [
      "coeff" => $row[$coeffCol] ?? null,
    ]);
  }

  function compareCodApr(array $a, array $b) {
    $comparator = cl::compare(["+0", "+1", "+2"]);
    return $comparator($a, $b);
  }

  function compareNom(array $a, array $b) {
    $comparator = cl::compare(["+1", "+2"]);
    return $comparator($a, $b);
  }

  protected ?IBuilder $builder = null;

  protected $output = null;

  abstract function compute(): static;

  protected abstract function writeRows(): void;

  protected function getBuilderParams(): ?array {
    return null;
  }

  function build($output): self {
    $this->compute();

    $this->output = $output;
    $params = cl::merge($this->getBuilderParams(), [
      "use_headers" => false,
    ]);
    $this->builder = SsBuilder::with($output, $params);
    $this->writeRows();
    $this->builder->build();
    return $this;
  }

  function write(): void {
    $this->builder->copyTo(file::writer($this->output), true);
    $this->builder = null;
    $this->output = null;
  }

  function send(bool $exit=true): void {
    $this->builder->sendFile();
    if ($exit) exit();
  }

  #############################################################################

  protected static function split_code_title(string &$title, ?string &$code=null): bool {
    $code = null;
    if (preg_match('/^(.*?) - (.*)/', $title, $ms)) {
      $code = $ms[1];
      $title = $ms[2];
      return true;
    }
    return false;
  }

  protected ?array $sessions = null;

  function getSessions(): array {
    if ($this->sessions === null) {
      $sessions = [];
      foreach ($this->pvData->sesCols as $ises => $ses) {
        if ($ses["is_session"]) {
          $sessions[$ises] = [$ises, $ses["title"]];
        }
      }
      $this->sessions = $sessions;
    }
    return $this->sessions;
  }

  protected ?array $objs = null;

  function getObjs(): array {
    if ($this->objs === null) {
      $objs = [];
      foreach ($this->pvData->gptObjs as $igpt => $gpt) {
        $gptTitle = $gpt["title"];
        if ($gptTitle !== null) $objs["$igpt"] = $gptTitle;
        foreach ($gpt["objs"] as $iobj => $obj) {
          if ($igpt !== 0 || $iobj !== 0) {
            $objs["$igpt.$iobj"] = $obj;
          }
        }
      }
      $this->objs = $objs;
    }
    return $this->objs;
  }

  const STD_COLS = [
    "acquis_col" => [1, "Aménagements / Acquis / CHC incomplet", true],
    "note_col" => [2, "Note", true],
    "res_col" => [3, "Résultat", true],
    "ects_col" => [4, "ECTS", true],
    "pj_col" => [5, "Points Jury", true],
  ];

  protected ?array $cols = null;

  function getCols(): array {
    if ($this->cols === null) {
      $pvData = $this->pvData;
      $cols = [];
      $lastIcol = 0;
      foreach (self::STD_COLS as [$icol, $label, $checked]) {
        $cols[$icol] = null;
        if ($icol > $lastIcol) $lastIcol = $icol;
      }
      $defined = [];
      $nextIcol = $lastIcol + 1;
      $sess = cl::merge($pvData->sesCols, $pvData->ctlCols);
      foreach ($sess as $ses) {
        foreach (self::STD_COLS as $ref => [$icol, $label, $checked]) {
          $col = $cols[$icol] ?? null;
          if ($ses[$ref] !== null && $col === null) {
            $cols[$icol] = [$icol, $label, $checked, $ref];
          }
        }
        foreach ($ses["cols"] as $label) {
          # vérifier que ce n'est pas une colonne standard
          $found = false;
          foreach (array_keys(self::STD_COLS) as $ref) {
            if ($ses[$ref] === $label) {
              $found = true;
              break;
            }
          }
          if ($found) continue;
          # vérifier que ce n'est pas déjà défini
          if ($defined[$label] ?? false) continue;
          # ajouter la nouvelle colonne
          $defined[$label] = true;
          $cols[$nextIcol] = [$nextIcol, $label, false, null];
          $nextIcol++;
        }
      }
      $this->cols = array_filter($cols, function ($col) {
        return $col !== null;
      });
    }
    return $this->cols;
  }

  function getShowCols(?array $icols=null, ?array $ses=null): ?array {
    $showCols = null;
    foreach ($this->getCols() as [$icol, $label, $checked, $ref]) {
      if ($icols !== null && !in_array($icol, $icols)) continue;
      if ($ref !== null) $label = $ses[$ref];
      if ($label === null) continue;
      if (in_array($label, $ses["cols"])) {
        $showCols[$label] = $ref;
      }
    }
    return $showCols;

  }

  abstract function checkForm(): bool;

  abstract function printForm(): void;

  abstract function doFormAction(?array $params=null): void;

  #############################################################################

  abstract function print(?array $params=null): void;
}

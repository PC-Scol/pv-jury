<?php
namespace app;

use nulib\A;
use nulib\cl;
use nulib\ext\tab\SsBuilder;
use nulib\file;
use nulib\file\tab\IBuilder;

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
    "ABS. JUS." => "ABJ",
  ];
  const ACQ_MAP = [
    "VALIDATION - EVALUATION" => "VAL-EVAL",
    "DISPENSE ASSIDUITE" => "DIS.ASS",
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

  protected static function get_col_index(?string $col, ?array $ses) {
    if ($ses === null || $col === null) return null;
    return $ses["rev_cols"][$col]["index"] ?? null;
  }

  protected static function get_first_index(?array $ses) {
    return self::get_col_index(cl::first($ses["cols"]), $ses);
  }

  protected static function get_col_value(?string $col, ?array $ses, ?array $row) {
    $colIndex = self::get_col_index($col, $ses);
    if ($row === null || $colIndex === null) return null;
    return $row[$colIndex] ?? null;
  }

  function getAcq(array $row, array $acq): array {
    $acquisCol = $acq["acquis_col"];
    $acquis = null;
    if ($acquisCol !== null) {
      $acquis = self::get_col_value($acquisCol, $acq, $row);
      if (preg_match('/CAPITALISÉ(?: \(\d{2}(\d{2})-\d{2}(\d{2})\))?/u', $acquis, $ms)) {
        $f = $ms[1] ?? null;
        $t = $ms[2] ?? null;
        if ($f && $t) $acquis = "CAP$f-$t";
        else $acquis = "CAP";
      } else {
        $acquis = cl::get(self::ACQ_MAP, $acquis, $acquis);
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
      $res = self::get_col_value($resCol, $ses, $row);
      $res = cl::get(self::RES_MAP, $res, $res);
    }
    if ($noteCol !== null) {
      $note = self::get_col_value($noteCol, $ses, $row);
      if (is_numeric($note)) {
        $note = bcnumber::with($note)->floatval(3);
      } elseif (is_string($note)) {
        if ($res === null) $res = cl::get(self::RES_MAP, $note, $note);
        $note = null;
      }
    }
    if ($ectsCol !== null) {
      $ects = self::get_col_value($ectsCol, $ses, $row);
      if (is_numeric($ects)) {
        $ects = bcnumber::with($ects)->numval(3);
      } elseif (is_string($ects)) {
        $ects = cl::get(self::RES_MAP, $ects, $ects);
      }
    }
    if ($pjCol !== null) {
      $pj = self::get_col_value($pjCol, $ses, $row);
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

  function getAcqNoteResEctsPjCoeffMention(array $row, array $ses, ?array $acq): array {
    if ($acq !== null) $acq = $this->getAcq($row, $acq);
    $noteResEctsPj = $this->getNoteResEctsPj($row, $ses);
    $coeffIndex = self::get_col_index("Coefficient", $ses);
    $mentionIndex = self::get_col_index("Mention", $ses);
    return cl::merge($acq, $noteResEctsPj, [
      "coeff" => $row[$coeffIndex] ?? null,
      "have_mention" => $mentionIndex !== null,
      "mention" => $row[$mentionIndex] ?? null,
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

  protected ?array $selectableObjs = null;

  function getSelectableObjs(): array {
    return $this->selectableObjs ??= array_slice($this->pvData->objs, 1);
  }

  const STD_COLS_KEYS = ["checked", "label", "order"];
  const STD_COLS = [
    "acquis" => [true, null, 1],
    "rang" => [false, "colonnes rangs", 2],
    "note" => [true, "colonnes notes", 3],
    "bareme" => [false, "colonnes barèmes", 3],
    "coeff" => [false, "colonnes coefficients", 3],
    "res" => [true, "colonnes résultats", 4],
    "ects" => [true, "colonnes ECTs", 5],
    "pj" => [true, "colonnes points jury", 6],
    "mention" => [true, "colonnes mention", 7],
    "divers" => [false, "autres colonnes", 8],
  ];

  protected ?array $stdCols = null;

  function getStdCols(): array {
    if ($this->stdCols !== null) return $this->stdCols;
    $pvData = $this->pvData;
    $sess = cl::merge($pvData->sesCols, $pvData->ctlCols);
    $stdCols = [];
    foreach ($sess as $ses) {
      foreach ($ses["cols"] as $col) {
        $type = $ses["rev_cols"][$col]["type"];
        $stdCols[$type][$col] = true;
      }
    }
    $index = 1;
    foreach ($stdCols as $type => &$stdCol) {
      $labels = array_keys($stdCol);
      $label = implode(", ", $labels);
      $stdCol = self::STD_COLS[$type];
      A::ensure_assoc($stdCol, self::STD_COLS_KEYS);
      if ($stdCol["label"] === null || count($labels) == 1) {
        $stdCol["label"] = $label;
      } else {
        $stdCol["label"] .= " ($label)";
      }
      $stdCol["type"] = $type;
      $stdCol["index"] = $index++;
    }; unset($stdCol);
    uasort($stdCols, cl::compare(["order", "index"]));
    return $this->stdCols = $stdCols;
  }

  function getShowCols(?array $types, array $ses): ?array {
    $showCols = null;
    foreach ($ses["cols"] as $col) {
      if ($types === null || in_array($ses["rev_cols"][$col]["type"], $types)) {
        $showCols[$col] = true;
      }
    }
    if ($showCols !== null) $showCols = array_keys($showCols);
    return $showCols;
  }

  abstract function checkForm(): bool;

  abstract function printForm(): void;

  abstract function doFormAction(?array $params=null): void;

  #############################################################################

  abstract function print(?array $params=null): void;
}

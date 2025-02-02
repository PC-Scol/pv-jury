<?php
namespace app;

use nulib\A;
use nulib\cl;
use nulib\cv;
use nulib\ext\spout\SpoutBuilder;
use nulib\str;
use nulib\ValueException;
use nur\v\vo;
use OpenSpout\Writer\XLSX\Options\HeaderFooter;
use OpenSpout\Writer\XLSX\Options\PageMargin;
use OpenSpout\Writer\XLSX\Options\PageOrder;
use OpenSpout\Writer\XLSX\Options\PageOrientation;
use OpenSpout\Writer\XLSX\Options\PageSetup;
use OpenSpout\Writer\XLSX\Options\PaperSize;

/**
 * Class Model1CsvBuilder: construire un document pouvant servir à faire une
 * délibération: sélection par session, et affichage en colonnes
 */
class CsvPvModel1Builder extends CsvPvBuilder {
  private ?int $ises = null;

  function setIses(int $ises): void {
    $ses = $this->pvData->sesCols[$ises] ?? null;
    ValueException::check_null($ses);
    $this->ises = $ises;
    $this->pvData->verifixed = false;
  }

  function getSuffix(): string {
    return $this->pvData->sesCols[$this->ises]["title"] ?? "";
  }

  const ORDER_MERITE = "note", ORDER_ALPHA = "nom";

  private string $order = self::ORDER_ALPHA;

  function setOrder(string $order): void {
    $this->order = $order;
  }

  private bool $excludeControles = false;

  function setExcludeControles(bool $excludeControles): void {
    $this->excludeControles = $excludeControles;
  }

  private bool $excludeUnlessHaveValue = false;

  function setExcludeUnlessHaveValue(bool $excludeUnlessHaveValue): void {
    $this->excludeUnlessHaveValue = $excludeUnlessHaveValue;
  }

  protected function verifixPvData(PVData $pvData): void {
    $data = $pvData->data;
    $pvData->ws = [
      "document" => null,
      "sheet_pv" => null,
      "sheet_totals" => null,
    ];
    $ws =& $pvData->ws;

    $haveGpt = false;
    $firstObj = true;
    $titleObj = null;
    $titleSes = null;
    $objs = [];
    # construire la liste des objets
    foreach ($data["gpts"] as $gpt) {
      if ($gpt["title"] !== null) $haveGpt = true;
      foreach ($gpt["objs"] as $obj) {
        if ($firstObj) {
          $titleObj = $obj["title"];
          self::split_code_title($titleObj);
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
        # filtrer ceux qui n'ont pas de données
        #XXX que faire si $firstObj && $obj["ses"] === null ???
        if ($firstObj || $obj["ses"] !== null) {
          if ($obj["ses"]["have_value"] || !$this->excludeUnlessHaveValue) {
            $objs[] = $obj;
          }
        }
        $firstObj = false;
      }
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

  function getSessions(): array {
    $sessions = [];
    foreach ($this->pvData->sesCols as $ises => $ses) {
      if ($ses["is_session"]) {
        $sessions[$ises] = [$ises, $ses["title"]];
      }
    }
    return $sessions;
  }

  protected function getBuilderParams(): ?array {
    $title = $this->pvData->ws["document"]["title"][1];
    $footer = htmlspecialchars("&L$title&RPage &P / &N");
    return [
      "spout" => [
        "->setColumnWidth" => [0.5, 1],
        "->setPageSetup" => [new PageSetup(PageOrientation::LANDSCAPE, PaperSize::A3, null, null, PageOrder::OVER_THEN_DOWN)],
        "->setPageMargin" => [new PageMargin(0.39, 0.39, 0.75, 0.39)],
        "->setHeaderFooter" => [new HeaderFooter(null, $footer)],
      ],
      "sheet" => [
        "different_odd_even" => false,
      ],
      "sheet_view" => [
        "->setFreezeRow" => 7,
        "->setFreezeColumn" => "E",
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
    "ACQUIS" => "ACQ",
    "NON ACQUIS" => "NON ACQ",
    "EN COURS D'ACQUISITION" => "ENC ACQ",
    "ABS. INJ." => "ABI",
    "ABS. JUS." => "ABS",
  ];

  private static function split_code_title(string &$title, ?string &$code=null): bool {
    $code = null;
    if (preg_match('/^(.*?) - (.*)/', $title, $ms)) {
      $code = $ms[1];
      $title = $ms[2];
      return true;
    }
    return false;
  }

  const BOLD_S = [
    "font" => ["bold" => true],
  ];
  const WRAP_S = ["wrap" => true];
  const NOWRAP_S = ["wrap" => false];
  const ROTATE_S = ["rotation" => 45];
  const LEFT_S = ["align" => "left"];
  const CENTER_S = ["align" => "center"];
  const RIGHT_S = ["align" => "right"];
  const BA_S = ["border" => "all thin"];
  const BL_S = ["border" => "left top bottom thin"];
  const BT_S = ["border" => "left top right thin"];
  const BB_S = ["border" => "right bottom left thin"];
  const BR_S = ["border" => "top right bottom thin"];
  const BLT_S = ["border" => "left top thin"];
  const BLB_S = ["border" => "left bottom thin"];
  const BTR_S = ["border" => "top right thin"];
  const BBR_S = ["border" => "right bottom thin"];
  const NUMBER_S = [
    "format" => "0.000",
  ];
  const NOTE_S = self::RIGHT_S;
  const CAP_S = self::CENTER_S;
  const RES_S = self::RIGHT_S;
  const ECTS_S = self::CENTER_S;

  function prepareLayout(): void {
    $ws =& $this->pvData->ws;
    $objs = $ws["objs"];
    $Spv =& $ws["sheet_pv"];

    $baS = cl::merge(self::BOLD_S, self::BA_S);
    $hrow = ["Code apprenant", "Nom", "Prénom"];
    $hrow_colsStyles = [cl::merge($baS, self::CENTER_S, self::WRAP_S), $baS, $baS];
    $firstObj = true;
    foreach ($objs as $obj) {
      $title = $obj["title"];
      if ($firstObj) {
        $firstObj = false;
        $hrow[] = "Note\nRésultat";
        $hrow_colsStyles[] = cl::merge(self::BOLD_S, self::BA_S, self::RES_S);
        $hrow[] = "PointsJury\nECTS";
        $hrow_colsStyles[] = cl::merge(self::BOLD_S, self::BA_S, self::ECTS_S);
      } else {
        if (!self::split_code_title($title, $code)) {
          $code = $title;
          $title = null;
        }
        $hrow[] = $code;
        $hrow_colsStyles[] = cl::merge(self::BOLD_S, self::BL_S, self::ROTATE_S, self::RIGHT_S, self::NOWRAP_S);
        $hrow[] = $title;
        $hrow_colsStyles[] = cl::merge(self::BOLD_S, self::BR_S, self::ROTATE_S, self::LEFT_S, self::WRAP_S);

        if (!$this->excludeControles && ($obj["ses"]["ctls"] ?? null) !== null) {
          foreach ($obj["ses"]["ctls"] as $ctl) {
            $title = $ctl["title"];
            $hrow[] = $title;
            $hrow_colsStyles[] = cl::merge(self::BOLD_S, self::BA_S, self::ROTATE_S, self::RIGHT_S, self::WRAP_S);
          }
        }
      }
    }
    $Spv["headers"] = [$hrow];
    $Spv["headers_cols_styles"] = [$hrow_colsStyles];
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

  function getNoteResEcts(array $row, array $ses): array {
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
      "note" => $note,
      "res" => $res,
      "ects" => $ects,
    ];
  }

  function getAcqNoteResEcts(array $row, array $obj, bool $pjInsteadOfAcq=false): array {
    $ses = $obj["ses"];
    $acq = ["acquis" => null];
    $pj = ["pj" => null];
    if ($pjInsteadOfAcq) {
      $pjCol = $ses["pj_col"];
      $pj = [
        "pj" => $pjCol !== null? $row[$ses["col_indexes"][$pjCol]]: null,
      ];
    } else {
      $acq = $this->getAcq($row, $obj["acq"]);
    }
    $noteResEcts = $this->getNoteResEcts($row, $ses);
    return cl::merge($acq, $noteResEcts, $pj);
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

  function addAcqNoteResEcts(
    array $row, array $obj, bool $pjInsteadOfAcq,
    array &$brow1, array &$brow1Styles,
    array &$brow2, array &$brow2Styles,
  ): array {
    [
      "acquis" => $acquis,
      "note" => $note,
      "res" => $res,
      "ects" => $ects,
      "pj" => $pj,
    ] = $this->getAcqNoteResEcts($row, $obj, $pjInsteadOfAcq);

    $nses = $ses["nses"] ?? null;
    if ($acquis !== null && $res === "AJ" && $nses !== null) {
      # bug: au 20/11/2024, PEGASE ne remonte pas les capitalisations des
      # années antérieures sur la bonne session
      [
        "note" => $nnote,
        "res" => $nres,
        "ects" => $nects,
      ] = $this->getNoteResEcts($row, $nses);
      if (str::starts_with("ADM", $nres)) {
        [$note, $res, $ects] = [$nnote, $nres, $nects];
      }
    }

    $brow1[] = $note;
    $brow1Styles[] = cl::merge(self::BLT_S, self::NOTE_S, self::NUMBER_S);
    $brow1[] = $pjInsteadOfAcq? $pj: $acquis;
    $brow1Styles[] = cl::merge(self::BTR_S, self::CAP_S, $pjInsteadOfAcq? self::NUMBER_S: null);
    $brow2[] = $res;
    $brow2Styles[] = cl::merge(self::BLB_S, self::RES_S);
    $brow2[] = $ects;
    $brow2Styles[] = cl::merge(self::BBR_S, self::ECTS_S);

    return [
      "acquis" => $acquis,
      "note" => $note,
      "res" => $res,
      "ects" => $ects,
    ];
  }

  function addNoteRes(
    array  $row, array $ctl,
    array  &$brow1, array &$brow1Styles,
    array  &$brow2, array &$brow2Styles,
  ): array {
    [
      "note" => $note,
      "res" => $res,
    ] = $this->getNoteResEcts($row, $ctl);

    $brow1[] = $note;
    $brow1Styles[] = cl::merge(self::BT_S, self::NOTE_S, self::NUMBER_S);
    $brow2[] = $res;
    $brow2Styles[] = cl::merge(self::BB_S, self::RES_S);

    return [
      "note" => $note,
      "res" => $res,
    ];
  }

  function parseRow(array $row): void {
    $ws =& $this->pvData->ws;
    $objs = $ws["objs"];
    $Spv =& $ws["sheet_pv"];
    $notes =& $ws["notes"];
    $resultats =& $ws["resultats"];
    $codApr = $row[0];

    $brow1 = array_slice($row, 0, 3);
    $brow1Styles = [
      cl::merge(self::BT_S, ["align" => "center"]),
      self::BT_S,
      self::BT_S,
    ];
    $brow2 = [null, null, null];
    $brow2Styles = [self::BB_S, self::BB_S, self::BB_S];
    $firstObj = true;
    foreach ($objs as $iobj => $obj) {
      [
        "note" => $note,
        "res" => $res,
      ] = $this->addAcqNoteResEcts($row, $obj, $firstObj, $brow1, $brow1Styles, $brow2, $brow2Styles);

      if ($note !== null) $notes[$iobj][$codApr] = $note;
      if ($firstObj && $res !== null) $resultats[$codApr] = $res;
      $firstObj = false;

      if (!$this->excludeControles && ($obj["ses"]["ctls"] ?? null) !== null) {
        foreach ($obj["ses"]["ctls"] as $ictl => $ctl) {
          [
            "note" => $note,
          ] = $this->addNoteRes($row, $ctl, $brow1, $brow1Styles, $brow2, $brow2Styles);
          if ($note !== null) $notes["$iobj+$ictl"][$codApr] = $note;
        }
      }
    }
    $Spv["body"][] = $brow1;
    $Spv["body_cols_styles"][] = $brow1Styles;
    $Spv["body"][] = $brow2;
    $Spv["body_cols_styles"][] = $brow2Styles;
  }

  private static function add_stat_brow(string $label, array $spans, array $notes, ?array &$footer, ?array &$footer_cols_styles): void {
    $frow = [null, null, $label];
    $frow_cols_styles = [null, null, self::BA_S];
    $noteS = cl::merge(self::BL_S, [
      "format" => "0.000",
    ]);
    foreach ($notes as $key => $note) {
      $span = $spans[$key];
      /** @var bcnumber $note */
      if ($note !== null) $note = $note->floatval(3);
      if ($span == 1) {
        $frow[] = $note;
        $frow_cols_styles[] = cl::merge($noteS, self::BA_S);
      } else {
        $frow[] = $note;
        $frow_cols_styles[] = $noteS;
        $frow[] = null;
        $frow_cols_styles[] = self::BR_S;
      }
    }
    $footer[] = $frow;
    $footer_cols_styles[] = $frow_cols_styles;
  }

  protected function addStat(?array $onotes, array &$stats, int $span): void {
    if ($onotes !== null) {
      [$min, $max, $avg] = bcnumber::min_max_avg($onotes, true);
      $stdev = bcnumber::stdev($onotes, true);
      $avg_stdev = $avg->sub($stdev);
    } else {
      $min = $max = $avg = $stdev = $avg_stdev = null;
    }
    $stats["spans"][] = $span;
    $stats["notes_min"][] = $min;
    $stats["notes_max"][] = $max;
    $stats["notes_avg"][] = $avg;
    $stats["stdev"][] = $stdev;
    $stats["avg_stdev"][] = $avg_stdev;
  }

  function computeStats(): void {
    $ws =& $this->pvData->ws;
    $objs = $ws["objs"];
    $Spv =& $ws["sheet_pv"];

    $stats = [
      "spans" => [],
      "notes_min" => [],
      "notes_max" => [],
      "notes_avg" => [],
      "stdev" => [],
      "avg_stdev" => [],
    ];
    $notes = $ws["notes"];
    foreach ($objs as $iobj => $obj) {
      $onotes = $notes[$iobj] ?? null;
      $this->addStat($onotes, $stats, 2);

      if (!$this->excludeControles && ($obj["ses"]["ctls"] ?? null) !== null) {
        foreach ($obj["ses"]["ctls"] as $ictl => $ctl) {
          $onotes = $notes["$iobj+$ictl"] ?? null;
          $this->addStat($onotes, $stats, 1);
        }
      }
    }

    self::add_stat_brow("Note min", $stats["spans"], $stats["notes_min"], $Spv["footer"], $Spv["footer_cols_styles"]);
    self::add_stat_brow("Note max", $stats["spans"], $stats["notes_max"], $Spv["footer"], $Spv["footer_cols_styles"]);
    self::add_stat_brow("Note moy", $stats["spans"], $stats["notes_avg"], $Spv["footer"], $Spv["footer_cols_styles"]);
    self::add_stat_brow("écart-type", $stats["spans"], $stats["stdev"], $Spv["footer"], $Spv["footer_cols_styles"]);
    self::add_stat_brow("moy - écart-type", $stats["spans"], $stats["avg_stdev"], $Spv["footer"], $Spv["footer_cols_styles"]);

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

    $nbS = cl::merge(self::BA_S, [
      "align" => "center",
    ]);
    $Stotals =& $ws["sheet_totals"];
    $Stotals["headers"] = [
      [null, "Nombre", "Pourcentage"],
    ];
    $Stotals["headers_cols_styles"] = [
      [null, $nbS, $nbS],
    ];
    $Stotals["body"] = [
      ["Nb étudiants", $totals["nb_apprenants"], null],
      ["Nb admis", $totals["nb_admis"], $totals["per_admis"]],
      ["Nb ajournés", $totals["nb_ajournes"], $totals["per_ajournes"]],
      ["Nb absents", $totals["nb_absents"], null],
    ];
    $perS = cl::merge(self::BA_S, [
      "format" => "0.00\\ %",
    ]);
    $Stotals["body_cols_styles"] = [
      [self::BA_S, $nbS, $perS],
      [self::BA_S, $nbS, $perS],
      [self::BA_S, $nbS, $perS],
      [self::BA_S, $nbS, $perS],
    ];
  }

  function compute(?PvData $pvData=null): static {
    $this->ensurePvData($pvData);

    A::merge($pvData->ws, [
      "sheet_pv" => [
        "headers" => null,
        "headers_cols_styles" => null,
        "body" => null,
        "body_cols_styles" => null,
        "footer" => null,
        "footer_cols_styles" => null,
      ],
      "sheet_totals" => [
        "headers" => null,
        "headers_cols_styles" => null,
        "body" => null,
        "body_cols_styles" => null,
      ],
    ]);
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
    /** @var SpoutBuilder $builder */
    $builder = $this->builder;
    $ws = $pvData->ws;

    foreach ($ws["document"]["title"] as $line) {
      $builder->write([$line]);
    }

    $Spv = $ws["sheet_pv"];
    $builder->write([]);
    $prefix = [null];
    $section_colsStyles = $Spv["headers_cols_styles"] ?? null;
    $section_rowStyles = $Spv["headers_row_styles"] ?? null;
    foreach ($Spv["headers"] as $key => $row) {
      $colsStyle = $section_colsStyles[$key] ?? null;
      if ($colsStyle !== null) $colsStyle = cl::merge($prefix, $colsStyle);
      $rowStyle = $section_rowStyles[$key] ?? null;
      if (($rowStyle["merge_cells"] ?? null) !== null) {
        $rowStyle["merge_offset"] = count($prefix);
      }
      $builder->write(cl::merge($prefix, $row), $colsStyle, $rowStyle);
    }
    $section_colsStyles = $Spv["body_cols_styles"] ?? null;
    $section_rowStyles = $Spv["body_row_styles"] ?? null;
    foreach ($Spv["body"] as $key => $row) {
      $colsStyle = $section_colsStyles[$key] ?? null;
      if ($colsStyle !== null) $colsStyle = cl::merge($prefix, $colsStyle);
      $rowStyle = $section_rowStyles[$key] ?? null;
      $builder->write(cl::merge($prefix, $row), $colsStyle, $rowStyle);
    }
    $section_colsStyles = $Spv["footer_cols_styles"] ?? null;
    $section_rowStyles = $Spv["footer_row_styles"] ?? null;
    foreach ($Spv["footer"] as $key => $row) {
      $colsStyle = $section_colsStyles[$key] ?? null;
      if ($colsStyle !== null) $colsStyle = cl::merge($prefix, $colsStyle);
      $rowStyle = $section_rowStyles[$key] ?? null;
      $builder->write(cl::merge($prefix, $row), $colsStyle, $rowStyle);
    }

    $Stotals = $ws["sheet_totals"];
    $builder->write([]);
    $prefix = [null, null, null];
    $section_colsStyles = $Stotals["headers_cols_styles"] ?? null;
    $section_rowStyles = $Spv["headers_row_styles"] ?? null;
    foreach ($Stotals["headers"] as $key => $row) {
      $colsStyle = $section_colsStyles[$key] ?? null;
      if ($colsStyle !== null) $colsStyle = cl::merge($prefix, $colsStyle);
      $rowStyle = $section_rowStyles[$key] ?? null;
      $builder->write(cl::merge($prefix, $row), $colsStyle, $rowStyle);
    }
    $section_colsStyles = $Stotals["body_cols_styles"] ?? null;
    $section_rowStyles = $Spv["body_row_styles"] ?? null;
    foreach ($Stotals["body"] as $key => $row) {
      $colsStyle = $section_colsStyles[$key] ?? null;
      if ($colsStyle !== null) $colsStyle = cl::merge($prefix, $colsStyle);
      $rowStyle = $section_rowStyles[$key] ?? null;
      $builder->write(cl::merge($prefix, $row), $colsStyle, $rowStyle);
    }

    $rowStyle = ["->setHeight" => 30];
    $builder->write([]);
    $prefix = [null, null, null];
    $builder->write(cl::merge($prefix, ["Le président du jury"]), null, $rowStyle);
    $builder->write(cl::merge($prefix, ["Date"]), null, $rowStyle);
    $builder->write(cl::merge($prefix, ["Les membres du jury"]), null, $rowStyle);
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
    vo::start("tfoot");
    foreach ($pv["footer"] as $row) {
      vo::str();
      foreach ($row as $col) {
        vo::td($col);
      }
      vo::etr();
    }
    vo::end("tfoot");
    vo::etable();
  }
}

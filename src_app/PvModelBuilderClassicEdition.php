<?php
namespace app;

use Exception;
use nulib\A;
use nulib\cl;
use nulib\cv;
use nulib\ext\spout\SpoutBuilder;
use nulib\os\path;
use nulib\str;
use nulib\ValueException;
use nur\v\al;
use nur\v\bs3\fo\Form;
use nur\v\bs3\fo\FormBasic;
use nur\v\plugins\showmorePlugin;
use nur\v\v;
use nur\v\vo;
use OpenSpout\Writer\XLSX\Options\HeaderFooter;
use OpenSpout\Writer\XLSX\Options\PageMargin;
use OpenSpout\Writer\XLSX\Options\PageOrder;
use OpenSpout\Writer\XLSX\Options\PageOrientation;
use OpenSpout\Writer\XLSX\Options\PageSetup;
use OpenSpout\Writer\XLSX\Options\PaperSize;

/**
 * Class PvModelBuilderClassicEdition: construire un document pouvant servir à
 * faire une délibération: sélection par session, et affichage en colonnes
 * (édition classique, inspiration APOGEE)
 */
class PvModelBuilderClassicEdition extends PvModelBuilder {
  private ?int $ises = null;

  function setIses($ises): static {
    $ises = cl::first(cl::with($ises));
    $ses = $this->pvData->sesCols[$ises] ?? null;
    ValueException::check_null($ses);
    $this->ises = $ises;
    return $this;
  }

  protected function getSuffix(): string {
    return $this->pvData->sesCols[$this->ises]["title"] ?? "";
  }

  private bool $addCoeffCol = false;

  function setAddCoeffCol(bool $addCoeffCol=true): static {
    $this->addCoeffCol = $addCoeffCol;
    return $this;
  }

  private bool $excludeControles = false;

  function setExcludeControles(bool $excludeControles=true): static {
    $this->excludeControles = $excludeControles;
    return $this;
  }

  private bool $excludeUnlessHaveValue = false;

  function setExcludeUnlessHaveValue(bool $excludeUnlessHaveValue=true): static {
    $this->excludeUnlessHaveValue = $excludeUnlessHaveValue;
    return $this;
  }

  private ?array $includeObjs = null;

  function setIncludeObjs(?array $includeObjs): static {
    if ($includeObjs !== null) {
      $objs = [[true]]; // toujours inclure 0.0
      foreach ($includeObjs as &$excludeObj) {
        [$igpt, $iobj] = str::split_pair($excludeObj, ".");
        $objs[$igpt][$iobj] = true;
      }
      $includeObjs = $objs;
    }
    $this->includeObjs = $includeObjs;
    return $this;
  }

  protected function shouldExcludeObj(array $obj): bool {
    if ($this->includeObjs === null) return false;
    $includeObjet = $this->includeObjs[$obj["igpt"]][$obj["iobj"]] ?? false;
    return !$includeObjet;
  }

  protected function preparePvData(PVData $pvData): void {
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
    foreach ($data["gpts"] as $igpt => $gpt) {
      if ($gpt["title"] !== null) $haveGpt = true;
      foreach ($gpt["objs"] as $iobj => $obj) {
        if ($firstObj) {
          $titleObj = $obj["title"];
          self::split_code_title($titleObj);
        }
        $obj["igpt"] = $igpt;
        $obj["iobj"] = $iobj;
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
        if ($firstObj) {
          # toujours inclure le premier objet
          $objs[] = $obj;
        } elseif ($obj["ses"] !== null) {
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

  function prepareLayout(): void {
    $ws =& $this->pvData->ws;
    A::merge($ws, [
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

    $objs = $ws["objs"];
    $Spv =& $ws["sheet_pv"];

    $baS = [
      "font" => ["bold" => true],
      "border" => "all thin",
    ];
    $hrow = ["Code apprenant", "Nom", "Prénom"];
    $hrow_colsStyles = [cl::merge($baS, ["align" => "center", "wrap" => true]), $baS, $baS];
    $firstObj = true;
    $addCoeffCol = $this->addCoeffCol;
    foreach ($objs as $obj) {
      if ($this->shouldExcludeObj($obj)) continue;
      $title = $obj["title"];
      if ($firstObj) {
        $firstObj = false;
        $hrow[] = "Note\nRésultat";
        $hrow_colsStyles[] = [
          "font" => ["bold" => true],
          "border" => "all thin",
          "align" => "right",
          "wrap" => true,
        ];
        $hrow[] = "PointsJury\nECTS";
        $hrow_colsStyles[] = [
          "font" => ["bold" => true],
          "border" => "all thin",
          "align" => "center",
          "wrap" => true,
        ];
        if ($addCoeffCol) {
          $hrow[] = "Coeff";
          $hrow_colsStyles[] = [
            "font" => ["bold" => true],
            "border" => "all thin",
            "align" => "center",
            "wrap" => true,
          ];
        }
      } else {
        if (!self::split_code_title($title, $code)) {
          $code = $title;
          $title = null;
        }
        $hrow[] = $code;
        $hrow_colsStyles[] = [
          "font" => ["bold" => true],
          "border" => "left top bottom thin",
          "rotation" => 70,
          "align" => "right",
          "wrap" => false,
        ];
        $hrow[] = $title;
        if ($addCoeffCol) {
          $hrow_colsStyles[] = [
            "font" => ["bold" => true],
            "border" => "top bottom thin",
            "rotation" => 70,
            "align" => "left",
            "wrap" => true,
          ];
          $hrow[] = null;
          $hrow_colsStyles[] = [
            "font" => ["bold" => true],
            "border" => "top right bottom thin",
            "rotation" => 70,
            "wrap" => true,
          ];
        } else {
          $hrow_colsStyles[] = [
            "font" => ["bold" => true],
            "border" => "top right bottom thin",
            "rotation" => 70,
            "align" => "left",
            "wrap" => true,
          ];
        }

        if (!$this->excludeControles && ($obj["ses"]["ctls"] ?? null) !== null) {
          foreach ($obj["ses"]["ctls"] as $ctl) {
            $title = $ctl["title"];
            $hrow[] = $title;
            $hrow_colsStyles[] = [
              "font" => ["bold" => true],
              "border" => "all thin",
              "rotation" => 70,
              "align" => "right",
              "wrap" => true,
            ];
          }
        }
      }
    }
    $Spv["headers"] = [$hrow];
    $Spv["headers_cols_styles"] = [$hrow_colsStyles];
    $Spv["headers_row_styles"] = [[
      "->setHeight" => 170, # 6cm
    ]];
  }

  function compareNote(array $rowa, array $rowb) {
    $obj = cl::first($this->pvData->ws["objs"]);
    ["note" => $notea,
    ] = $this->getAcqNoteResEctsPjCoeff($rowa, $obj["ses"], $obj["acq"]);
    ["note" => $noteb,
    ] = $this->getAcqNoteResEctsPjCoeff($rowb, $obj["ses"], $obj["acq"]);
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
      "coeff" => $coeff,
    ] = $this->getAcqNoteResEctsPjCoeff($row, $obj["ses"], $obj["acq"]);

    $nses = $ses["nses"] ?? null;
    if ($acquis !== null && $res === "AJ" && $nses !== null) {
      # bug: au 20/11/2024, PEGASE ne remonte pas les capitalisations des
      # années antérieures sur la bonne session
      [
        "note" => $nnote,
        "res" => $nres,
        "ects" => $nects,
      ] = $this->getNoteResEctsPj($row, $nses);
      if (str::starts_with("ADM", $nres)) {
        [$note, $res, $ects] = [$nnote, $nres, $nects];
      }
    }

    $brow1[] = $note;
    $brow1Styles[] = [
      "border" => "left top thin",
      "align" => "right",
      "format" => "0.000",
    ];
    if ($pjInsteadOfAcq || $acquis === null) {
      $brow1[] = $pj;
      $brow1Styles[] = [
        "border" => "top right thin",
        "align" => "center",
        "format" => "0.000",
      ];
    } else {
      $brow1[] = $acquis;
      $brow1Styles[] = [
        "border" => "top right thin",
        "align" => "center",
      ];
    }
    $addCoeffCol = $this->addCoeffCol;
    if ($addCoeffCol) {
      $brow1[] = $coeff;
      $brow1Styles[] = [
        "border" => "left top right thin",
        "align" => "center",
        "format" => "0",
      ];
    }
    $brow2[] = $res;
    $brow2Styles[] = [
      "border" => "left bottom thin",
      "align" => "right",
    ];
    $brow2[] = $ects;
    $brow2Styles[] = [
      "border" => "right bottom thin",
      "align" => "center",
    ];
    if ($addCoeffCol) {
      $brow2[] = null;
      $brow2Styles[] = [
        "border" => "left bottom right thin",
      ];
    }

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
    ] = $this->getNoteResEctsPj($row, $ctl);

    $brow1[] = $note;
    $brow1Styles[] = [
      "border" => "left top right thin",
      "align" => "right",
      "format" => "0.000",
    ];
    $brow2[] = $res;
    $brow2Styles[] = [
      "border" => "right bottom left thin",
      "align" => "right",
    ];

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
      [
        "border" => "left top right thin",
        "align" => "center",
      ],
      [
        "border" => "left top right thin",
      ],
      [
        "border" => "left top right thin",
      ],
    ];
    // forcer une valeur pour que la ligne ne soit jamais vide
    // la mettre en blanc sur blanc pour qu'elle soit invisible
    $brow2 = [null, null, "."];
    $brow2Styles = [
      [
        "border" => "right bottom left thin",
      ],
      [
        "border" => "right bottom left thin"
      ],
      [
        "border" => "right bottom left thin",
        "align" => "right",
        "font" => ["color" => "white"],
        "bg_color" => "white",
      ],
    ];
    $firstObj = true;
    foreach ($objs as $iobj => $obj) {
      if ($this->shouldExcludeObj($obj)) continue;
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
    $frow_cols_styles = [null, null, ["border" => "all thin"]];
    foreach ($notes as $key => $note) {
      $span = $spans[$key];
      /** @var bcnumber $note */
      if ($note !== null) $note = $note->floatval(3);
      if ($span == 1) {
        $frow[] = $note;
        $frow_cols_styles[] = [
          "border" => "all thin",
          "format" => "0.000",
        ];
      } else {
        $frow[] = $note;
        $frow_cols_styles[] = [
          "border" => "left top bottom thin",
          "format" => "0.000",
        ];
        $frow[] = null;
        $frow_cols_styles[] = [
          "border" => "top right bottom thin",
        ];
        if ($span > 2) {
          $frow[] = null;
          $frow_cols_styles[] = [
            "border" => "all thin",
          ];
        }
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
      if ($this->shouldExcludeObj($obj)) continue;
      $onotes = $notes[$iobj] ?? null;
      $this->addStat($onotes, $stats, $this->addCoeffCol? 3: 2);

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

    $nbS = [
      "border" => "all thin",
      "align" => "center",
    ];
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
    $perS = [
      "border" => "all thin",
      "format" => "0.00\\ %",
    ];
    $Stotals["body_cols_styles"] = [
      [["border" => "all thin"], $nbS, $perS],
      [["border" => "all thin"], $nbS, $perS],
      [["border" => "all thin"], $nbS, $perS],
      [["border" => "all thin"], $nbS, $perS],
    ];
  }

  function compute(): static {
    $pvData = $this->pvData;
    $this->preparePvData($pvData);
    $this->prepareLayout();

    $rows = $pvData->rows;
    switch ($this->order) {
    case self::ORDER_CODAPR:
      usort($rows, [self::class, "compareCodApr"]);
      break;
    case self::ORDER_ALPHA:
      usort($rows, [self::class, "compareNom"]);
      break;
    case self::ORDER_MERITE:
      usort($rows, [self::class, "compareNote"]);
      break;
    }
    foreach ($rows as $row) {
      $this->parseRow($row);
    }

    $this->computeStats();
    return $this;
  }

  protected function writeRows(): void {
    $pvData = $this->pvData;
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
    $section_rowStyles = $Stotals["headers_row_styles"] ?? null;
    foreach ($Stotals["headers"] as $key => $row) {
      $colsStyle = $section_colsStyles[$key] ?? null;
      if ($colsStyle !== null) $colsStyle = cl::merge($prefix, $colsStyle);
      $rowStyle = $section_rowStyles[$key] ?? null;
      $builder->write(cl::merge($prefix, $row), $colsStyle, $rowStyle);
    }
    $section_colsStyles = $Stotals["body_cols_styles"] ?? null;
    $section_rowStyles = $Stotals["body_row_styles"] ?? null;
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

  #############################################################################

  protected ?Form $form = null;

  function getForm(): Form {
    if ($this->form === null) {
      $sessions = $this->getSessions();
      $this->form = new FormBasic([
        "method" => "post",
        "schema" => [
          "ises" => ["?int", null, "Session"],
          "order" => ["string", null, "Ordre"],
          "xc" => ["bool", null, "NE PAS inclure les controles"],
          "xe" => ["bool", null, "Exclure les objets pour lesquels il n'y a ni note ni résultat"],
          "objs" => ["array", [], "Objets à inclure dans l'édition"],
        ],
        "params" => [
          "convert" => ["control" => "hidden", "value" => 1],
          "ises" => cl::merge([
            "control" => "select",
            "items" => $sessions,
          ], count($sessions) > 1? [
            "no_item_value" => "",
            "no_item_text" => "-- Veuillez choisir la session --",
          ]: null),
          "xc" => [
            "control" => "hidden",
            "value" => false,
            //"control" => "checkbox",
            //"value" => 1,
          ],
          "order" => [
            "control" => "select",
            "items" => [
              [self::ORDER_MERITE, "Classer par mérite (note)"],
              [self::ORDER_ALPHA, "Classer par ordre alphabétique (nom)"],
              [self::ORDER_CODAPR, "Classer par numéro apprenant"],
            ],
          ],
          "xe" => [
            "control" => "checkbox",
            "value" => 1,
          ],
          "objs" => false,
        ],
        "submit" => [
          "Editer le PV",
          "name" => "action",
          "value" => "convert",
          "accesskey" => "s",
          "class" => "btn-primary"
        ],
        "submitted_key" => "convert",
        "autoload_params" => true,
      ]);
    }
    return $this->form;
  }

  function checkForm(): bool {
    $form = $this->getForm();
    if ($form->isSubmitted()) {
      al::reset();
      if ($form["ises"] === null) {
        al::error("Vous devez choisir la session");
        return false;
      }
    }
    return true;
  }

  function printForm(): void {
    $form = $this->getForm();
    $form->printAlert();
    $form->printStart();
    $form->printControl("convert");
    $form->printControl("ises");
    $form->printControl("xc");
    $form->printControl("order");

    $sm = new showmorePlugin();
    $sm->printStartc();
    vo::p([
      "<em>Exclusion d'objets maquettes</em> : ",
      "vous pouvez exclure certains objets de l'édition du PV. ",
      $sm->invite("Afficher la liste des objets maquettes..."),
    ]);
    $sm->printStartp();
    $form->printControl("xe");
    vo::sdiv(["class" => "form-group"]);
    foreach ($this->getObjs() as $iobj => $obj) {
      if (str_contains($iobj, ".")) {
        $form->printCheckbox("Inclure $obj", "objs[]", $iobj, true, [
          "naked" => true,
          "naked_label" => true,
        ]);
      } else {
        vo::p(v::b($obj));
      }
    }
    vo::ediv();
    $sm->printEnd();

    $form->printControl("");
    $form->printEnd();
  }

  function doFormAction(?array $params=null): void {
    $form = $this->getForm();
    if ($params["add_coeff_col"] ?? false) $this->setAddCoeffCol();
    $this->setIses($form["ises"]);
    $this->setOrder($form["order"]);
    $this->setExcludeControles(boolval($form["xc"]));
    $this->setExcludeUnlessHaveValue(boolval($form["xe"]));
    $this->setIncludeObjs($form["objs"] ?? []);
    $suffix = $this->getSuffix();
    $output = path::filename($this->pvData->origname);
    $output = path::ensure_ext($output, "-$suffix.xlsx", ".csv");
    try {
      $this->build($output)->send();
    } catch (Exception $e) {
      al::error($e->getMessage());
    }
  }

  #############################################################################

  function printSession(): void {
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

  function print(?array $params=null): void {
    $pvData = $this->pvData;
    $this->setExcludeUnlessHaveValue(true);
    if ($params["add_coeff_col"] ?? false) $this->setAddCoeffCol();
    foreach ($this->getSessions() as [$ises, $session]) {
      $this->setIses($ises);
      $this->compute();
      vo::h2($session);
      if ($pvData->ws["resultats"] === null) {
        vo::p([
          "class" => "alert alert-warning",
          "Pour information, le fichier ne contient pas de résultats sur l'objet délibéré. L'encart 'nb étudiants/admis/ajournés' sera vide lors de l'édition"
        ]);
      }
      $this->printSession();
    }
  }
}

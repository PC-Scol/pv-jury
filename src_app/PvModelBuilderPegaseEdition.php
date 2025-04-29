<?php
namespace app;

use Exception;
use nulib\A;
use nulib\cl;
use nulib\cv;
use nulib\ext\spout\SpoutBuilder;
use nulib\os\path;
use nulib\str;
use nur\b\values\Breaker;
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

class PvModelBuilderPegaseEdition extends PvModelBuilder {
  private ?array $isess = null;

  function setIses($ises): static {
    $this->isess = array_map(function ($value) {
      return intval($value);
    }, cl::with($ises));
    return $this;
  }

  function shouldExcludeIses(int $ises): bool {
    if ($this->isess === null) return false;
    return !in_array($ises, $this->isess);
  }

  protected function getSuffix(): string {
    $sesTitles = [];
    foreach ($this->getSessions() as [$ises, $sesTitle]) {
      if (!$this->shouldExcludeIses($ises)) {
        $sesTitles[] = $sesTitle;
      }
    }
    return implode(",", $sesTitles);
  }

  private ?array $icols = null;

  function setIcols($icols): static {
    $this->icols = $icols;
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
      $objs = [[true]]; // toujours inclure 0
      foreach ($includeObjs as $includeObj) {
        $objs[$includeObj] = true;
      }
      $includeObjs = $objs;
    }
    $this->includeObjs = $includeObjs;
    return $this;
  }

  protected function shouldExcludeObj(array $obj): bool {
    if ($this->includeObjs === null) return false;
    $includeObjet = $this->includeObjs[$obj["iobj"]] ?? false;
    return !$includeObjet;
  }

  protected function preparePvData(PVData $pvData): void {
    $pvData->ws = [
      "objs" => null,
      "show_cols_sizes" => null,
      "document" => null,
      "sheet_pv" => null,
      "sheet_totals" => null,
    ];

    $data = $pvData->data;
    $ws =& $pvData->ws;

    $firstObj = true;
    $titleObj = null;
    $titleSes = null;
    $objs = [];
    # construire la liste des objets
    foreach ($data["objs"] as $obj) {
      if ($firstObj) {
        $titleObj = $obj["title"];
        self::split_code_title($titleObj);
      }
      # ne garder que les acquis et les sessions sélectionnées
      $sess = null;
      $sesTitles = [];
      foreach ($obj["sess"] as $ises => $ses) {
        if ($titleSes === null) {
          $sesTitle = $ses["title"];
          if ($sesTitle !== null) $sesTitles[] = $sesTitle;
        }
        # filtrer les "sessions"
        // les acquis sont toujours sélectionnés
        // les contrôles ne sont jamais sélectionnés
        if ($ses["is_controle"]) continue;
        // les sessions sont sélectionnées si l'utilisateur le demande
        if ($ses["is_session"] && $this->shouldExcludeIses($ises)) {
          $ses["show_cols"] = null;
        } else {
          # calculer les colonnes à afficher
          // pour la session
          $ses["show_cols"] = $this->getShowCols($this->icols, $ses);
          // pour les controles
          $ctls = $ses["ctls"] ?? null;
          if ($ctls !== null) {
            $showCtls = null;
            foreach ($ctls as $ctl) {
              $ctl["show_cols"] = $this->getShowCols($this->icols, $ctl);
              if ($ctl["show_cols"] !== null) $showCtls[] = $ctl;
            }
            if ($showCtls !== null) $ses["ctls"] = $showCtls;
            else unset($ses["ctls"]);
          }
        }
        $sess[$ises] = $ses;
      }
      $obj["sess"] = $sess;
      if ($titleSes === null) $titleSes = implode(", ", $sesTitles);
      # filtrer ceux qui n'ont pas de données
      if ($firstObj) {
        # toujours inclure le premier objet
        $includeObj = true;
      } elseif ($sess === null) {
        $includeObj = false;
      } elseif ($this->excludeUnlessHaveValue) {
        $haveValue = false;
        foreach ($obj["sess"] as $ses) {
          if ($ses["have_value"]) {
            $haveValue = true;
            break;
          }
        }
        $includeObj = $haveValue;
      } else {
        $includeObj = true;
      }
      if ($includeObj) $objs[] = $obj;
      $firstObj = false;
    }
    $ws["objs"] = $objs;

    $ws["document"]["title"] = [
      cl::first($data["title"]),
      $titleObj,
      $titleSes,
      cl::last($data["title"]),
    ];
  }

  protected function getBuilderParams(): ?array {
    $pvData = $this->pvData;
    $haveGpts = $pvData->data["have_gpts"];
    $title = $pvData->ws["document"]["title"][1];
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
        "->setFreezeRow" => $haveGpts? 10: 9,
        "->setFreezeColumn" => "E",
      ],
    ];
  }

  const BLANK_S = [
    "align" => "right",
    "font" => ["color" => "white"],
    "bg_color" => "white",
  ];

  const HCOL_OBJ = 0, HCOL_PARENT = 1, HCOL_CHILD = 2;

  const HROW_BORDERS = [
    "without_gpts" => [
      self::HCOL_OBJ => [
        "one" => [1 => "all medium"],
        "left" => [1 => "top left bottom medium"],
        "middle" => [1 => "top bottom medium"],
        "right" => [1 => "top right bottom medium"],
      ],
    ],
    "with_gpts" => [
      self::HCOL_OBJ => [
        "one" => ["left top right medium", "left bottom right medium"],
        "left" => ["left top medium", "left bottom medium"],
        "middle" => ["top medium", "bottom medium"],
        "right" => ["top right medium", "right bottom medium"],
      ],
      self::HCOL_PARENT => [
        "one" => ["left top medium", "left bottom right medium"],
        "left" => ["left top medium", "left bottom medium"],
        "middle" => ["top medium", "bottom medium"],
        "right" => ["top medium", "right bottom medium"],
      ],
      self::HCOL_CHILD => [
        "one" => ["top right medium", "all thin"],
        "left" => ["top medium", "top medium, left bottom thin"],
        "middle" => ["top medium", "top medium, bottom thin"],
        "right" => ["top right medium", "top medium, right bottom thin"],
      ],
    ],
  ];

  protected static function addHcol(
    $value, ?array $colStyles,
    array &$hrow, array &$hrow_colsStyles,
    ?int &$colIndex=null, ?int $maxIndex=null, ?bool $oneCol=null, bool $incColIndex=true,
    ?array $thicknessMap=null, bool $haveGpts=false, int $hcolType=self::HCOL_OBJ, int $hrowNum=1,
    ?string $hcolKey=null,
  ): void {
    if ($colIndex !== null) {
      $gptKey = $haveGpts? "with_gpts" : "without_gpts";
      if (($hcolKey === null && $oneCol) || $hcolKey === "one") {
        $border = self::HROW_BORDERS[$gptKey][$hcolType]["one"][$hrowNum];
        $border = str::replace($border, $thicknessMap);
        A::merge($colStyles, [
          "font" => ["bold" => true],
          "border" => $border,
        ]);
      } elseif (($hcolKey === null && $colIndex == 0) || $hcolKey === "left") {
        $border = self::HROW_BORDERS[$gptKey][$hcolType]["left"][$hrowNum];
        $border = str::replace($border, $thicknessMap);
        A::merge($colStyles, [
          "font" => ["bold" => true],
          "border" => $border,
        ]);
      } elseif (($hcolKey === null && $colIndex == $maxIndex) || $hcolKey === "right") {
        $border = self::HROW_BORDERS[$gptKey][$hcolType]["right"][$hrowNum];
        $border = str::replace($border, $thicknessMap);
        A::merge($colStyles, [
          "font" => ["bold" => true],
          "border" => $border,
        ]);
      } else {
        $border = self::HROW_BORDERS[$gptKey][$hcolType]["middle"][$hrowNum];
        $border = str::replace($border, $thicknessMap);
        A::merge($colStyles, [
          "font" => ["bold" => true],
          "border" => $border,
        ]);
      }
      if ($incColIndex) $colIndex++;
    }
    $hrow[] = $value;
    $hrow_colsStyles[] = $colStyles;
  }

  protected function addHrow23(
    array $ses,
    array &$hrow2, array &$hrow2_colsStyles,
    array &$hrow3, array &$hrow3_colsStyles,
    int $headersIndex=1,
  ): int {
    $headers = $this->pvData->headers;
    $showCols = $ses["show_cols"];
    $count2 = count($showCols);
    $index2 = cl::first($ses["col_indexes"]);
    $colIndex2 = 0;
    $maxIndex2 = $count2 - 1;
    $oneCol2 = $colIndex2 === $maxIndex2;
    $firstCol = true;
    foreach ($showCols as $col => $ref) {
      $value = $headers[$headersIndex][$index2++];
      if ($firstCol) $value ??= " ";
      $colStyles = null;
      self::addHcol(
        $value, $colStyles, $hrow2, $hrow2_colsStyles,
        $colIndex2, $maxIndex2, $oneCol2, true,
        ["medium" => "thin"],
      );
      $firstCol = false;

      $value = $headers[$headersIndex + 1][$ses["col_indexes"][$col]];
      $colStyles = [
        "font" => ["bold" => true],
        "border" => "all thin",
        "align" => "center", "wrap" => true,
      ];
      self::addHcol($value, $colStyles, $hrow3, $hrow3_colsStyles);
    }
    return $count2;
  }

  protected function prepareLayout(): void {
    $pvData = $this->pvData;
    $haveGpts = $pvData->data["have_gpts"];
    $headers = $pvData->headers;

    $ws =& $pvData->ws;
    A::merge($ws, [
      "sheet_pv" => [
        "headers" => [],
        "headers_cols_styles" => null,
        "body" => [],
        "body_cols_styles" => null,
        "footer" => [],
        "footer_cols_styles" => null,
      ],
      "sheet_totals" => [
        "headers" => [],
        "headers_cols_styles" => null,
        "body" => [],
        "body_cols_styles" => null,
      ],
    ]);
    $objs = $ws["objs"];
    $showColsSizes =& $ws["show_cols_sizes"];
    $Spv =& $ws["sheet_pv"];

    $hrow0 = [null, null, null];
    $hrow0_colsStyles = [
      ["border" => "left top thin"],
      ["border" => "top thin"],
      ["border" => "top right thin"],
    ];
    $hrow1 = [null, null, null];
    if ($haveGpts) {
      $hrow1_colsStyles = [
        ["border" => "left thin"],
        null,
        ["border" => "right thin"],
      ];
    } else {
      $hrow1_colsStyles = [
        ["border" => "left top thin"],
        ["border" => "top thin"],
        ["border" => "top right thin"],
      ];
    }
    $hrow2 = [null, null, null];
    $hrow2_colsStyles = [
      ["border" => "left thin"],
      null,
      ["border" => "right thin"],
    ];
    $hrow3 = ["Code apprenant", "Nom", "Prénom"];
    $hrow3_colsStyles = [
      [
        "font" => ["bold" => true],
        "border" => "left bottom thin",
        "align" => "center", "wrap" => true,
      ],
      [
        "font" => ["bold" => true],
        "border" => "bottom thin",
        "align" => "center", "wrap" => true,
      ],
      [
        "font" => ["bold" => true],
        "border" => "bottom right thin",
        "align" => "center", "wrap" => true,
      ],
    ];
    $index1 = 0;
    $breaker = new Breaker();
    $gptTitle = null;
    foreach ($objs as $iobj => $obj) {
      if ($this->shouldExcludeObj($obj)) continue;
      $firstSes = true;
      $count1 = 0;
      $headersIndex = $haveGpts? 2: 1;
      foreach ($obj["sess"] as $ises => $ses) {
        if ($firstSes) {
          $index1 = cl::first($ses["col_indexes"]);
          $firstSes = false;
        }
        $showCols = $ses["show_cols"] ?? null;
        if ($showCols === null) continue;
        $showColsSize = $this->addHrow23($ses, $hrow2, $hrow2_colsStyles, $hrow3, $hrow3_colsStyles, $headersIndex);
        $showColsSizes[$iobj]["ses_sizes"][$ises] = $showColsSize;
        $count1 += $showColsSize;
        $ctls = $ses["ctls"] ?? null;
        if ($ctls !== null) {
          foreach ($ctls as $ictl => $ctl) {
            $showColsSize = $this->addHrow23($ctl, $hrow2, $hrow2_colsStyles, $hrow3, $hrow3_colsStyles, $headersIndex);
            $showColsSizes[$iobj]["ctl_sizes"][$ictl] = $showColsSize;
            $count1 += $showColsSize;
          }
        }
      }

      $showColsSizes[$iobj]["size"] = $count1;
      $colIndex1 = 0;
      $maxIndex1 = $count1 - 1;
      $oneCol1 = $colIndex1 === $maxIndex1;

      if ($obj["gpt_parent"]) {
        $hcolType0 = self::HCOL_PARENT;
        $gptTitle = null;
        $colIndex0 = 0;
        $incColIndex0 = false;
        $maxIndex0 = $obj["gpt_count"];
        $oneCol0 = $colIndex0 === $maxIndex0;
        if ($oneCol0) $hcolKey0 = "one";
        else $hcolKey0 = "left";
        $hcolType1 = self::HCOL_PARENT;
      } elseif ($obj["gpt_title"] !== null) {
        $hcolType0 = self::HCOL_CHILD;
        if ($breaker->shouldBreakOn($obj["gpt_title"])) {
          $gptTitle = $obj["gpt_title"];
        }
        $colIndex0++;
        $hcolType1 = self::HCOL_CHILD;
      } else {
        $hcolType0 = self::HCOL_OBJ;
        $colIndex0 = 0;
        $incColIndex0 = true;
        $maxIndex0 = $maxIndex1;
        $oneCol0 = $oneCol1;
        $hcolKey0 = null;
        $hcolType1 = self::HCOL_OBJ;
      }

      $headersIndex = $haveGpts? 1: 0;
      for ($i = 0; $i < $count1; $i++) {
        if ($haveGpts) {
          if ($hcolKey0 === "middle" && $colIndex0 == $maxIndex0 && $colIndex1 == $maxIndex1) {
            $hcolKey0 = "right";
          }
          self::addHcol(
            $gptTitle, null, $hrow0, $hrow0_colsStyles,
            $colIndex0, $maxIndex0, $oneCol0, $incColIndex0,
            null, $haveGpts, $hcolType0, 0,
            $hcolKey0,
          );
          $gptTitle = null;
          if ($hcolKey0 === "left") $hcolKey0 = "middle";
        }

        $value = $headers[$headersIndex][$index1++];
        $colStyles = null;
        self::addHcol(
          $value, $colStyles, $hrow1, $hrow1_colsStyles,
          $colIndex1, $maxIndex1, $oneCol1, true,
          null, $haveGpts, $hcolType1,
        );
      }
    }
    // mettre un espace pour que le contenu de la dernière colonne ne déborde pas
    $hrow1[] = " ";
    $hrow2[] = " ";

    $headers = [];
    $headers_colsStyles = [];
    if ($haveGpts) {
      $headers[] = $hrow0;
      $headers_colsStyles[] = $hrow0_colsStyles;
    }
    A::merge($headers, [$hrow1, $hrow2, $hrow3]);
    A::merge($headers_colsStyles, [$hrow1_colsStyles, $hrow2_colsStyles, $hrow3_colsStyles]);
    $Spv["headers"] = $headers;
    $Spv["headers_cols_styles"] = $headers_colsStyles;
  }

  function compareNote(array $rowa, array $rowb) {
    $obj = cl::first($this->pvData->ws["objs"]);
    $acq = null;
    $ses = null;
    foreach ($obj["sess"] as $ses) {
      if ($ses["title"] === null) $acq = $ses;
      else break;
    }
    ["note" => $notea,
    ] = $this->getAcqNoteResEctsPjCoeff($rowa, $ses, $acq);
    ["note" => $noteb,
    ] = $this->getAcqNoteResEctsPjCoeff($rowb, $ses, $acq);
    if (!is_numeric($notea)) $notea = -1;
    if (!is_numeric($noteb)) $noteb = -1;
    $c = -cv::compare($notea, $noteb);
    if ($c !== 0) return $c;
    else return $this->compareNom($rowa, $rowb);
  }

  protected function addBrow(
    array  $row, array $ses, ?array $acq,
    array  &$brow, array &$browColsStyles,
    string $codApr, array &$snotes, int &$inote,
    ?array &$resultats=null, ?bool &$firstObj=null,
  ): void {
    [
      "acquis" => $acquis,
      "note" => $note,
      "res" => $res,
      "ects" => $ects,
      "pj" => $pj,
    ] = $this->getAcqNoteResEctsPjCoeff($row, $ses, $acq);

    $showCols = $ses["show_cols"];
    foreach ($showCols as $col => $ref) {
      $colStyles = ["border" => "all thin"];
      $snote = null;
      switch ($ref) {
      case "acquis_col":
        $value = $acquis;
        A::merge($colStyles, [
          "align" => "center",
        ]);
        break;
      case "note_col":
        $snote = $note;
        $value = $note;
        A::merge($colStyles, [
          "align" => "right",
          "format" => "0.000",
        ]);
        break;
      case "res_col":
        if ($firstObj && $res !== null) {
          $resultats[$codApr] = $res;
          $firstObj = false;
        }
        $value = $res;
        A::merge($colStyles, [
          "align" => "center",
        ]);
        break;
      case "ects_col":
        $value = $ects;
        A::merge($colStyles, [
          "align" => "center",
        ]);
        break;
      case "pj_col":
        $value = $pj;
        A::merge($colStyles, [
          "align" => "center",
          "format" => "0.000",
        ]);
        break;
      default:
        $value = $row[$ses["col_indexes"][$col]];
        if (is_float($value)) {
          A::merge($colStyles, [
            "align" => "right",
            "format" => "0.000",
          ]);
        } elseif (is_int($value)) {
          A::merge($colStyles, [
            "align" => "center",
            "format" => "0",
          ]);
        } else {
          A::merge($colStyles, [
            "align" => "center",
          ]);
        }
        break;
      }
      $brow[] = $value;
      $browColsStyles[] = $colStyles;
      A::ensure_narray($snotes[$inote]);
      if (is_float($snote)) $snotes[$inote][$codApr] = $snote;
      $inote++;
    }
  }

  function parseRow(array $row): void {
    $ws =& $this->pvData->ws;
    $objs = $ws["objs"];
    $Spv =& $ws["sheet_pv"];
    $snotes =& $ws["snotes"];
    $resultats =& $ws["resultats"];
    $codApr = $row[0];

    $brow = array_slice($row, 0, 3);
    $browColsStyles = [
      [
        "border" => "left top bottom thin",
        "align" => "center",
      ],
      [
        "border" => "top bottom thin",
      ],
      [
        "border" => "top right bottom thin",
      ],
    ];
    $firstObj = true;
    $inote = 0;
    $snotes ??= [];
    foreach ($objs as $obj) {
      if ($this->shouldExcludeObj($obj)) continue;
      $acq = null;
      foreach ($obj["sess"] as $ses) {
        if ($ses["title"] === null) $acq = $ses;
        $showCols = $ses["show_cols"];
        if ($showCols === null) continue;
        $this->addBrow(
          $row, $ses, $acq, $brow, $browColsStyles,
          $codApr, $snotes, $inote,
          $resultats, $firstObj,
        );
        $ctls = $ses["ctls"] ?? null;
        if ($ctls !== null) {
          foreach ($ctls as $ctl) {
            $this->addBrow($row, $ctl, $acq, $brow, $browColsStyles, $codApr, $snotes, $inote);
          }
        }
      }
    }
    $Spv["body"][] = $brow;
    $Spv["body_cols_styles"][] = $browColsStyles;
  }

  private static function add_stat_brow(string $label, array $notes, ?array &$footer, ?array &$footer_cols_styles): void {
    $frow = [null, null, $label];
    $frow_cols_styles = [null, null, ["border" => "all thin"]];
    foreach ($notes as $note) {
      /** @var bcnumber $note */
      if ($note !== null) $note = $note->floatval(3);
      $frow[] = $note;
      $frow_cols_styles[] = [
        "border" => "all thin",
        "format" => "0.000",
      ];
    }
    $footer[] = $frow;
    $footer_cols_styles[] = $frow_cols_styles;
  }

  function computeStats(): void {
    $ws =& $this->pvData->ws;
    $Spv =& $ws["sheet_pv"];

    $stats = [
      "notes_min" => [],
      "notes_max" => [],
      "notes_avg" => [],
      "stdev" => [],
      "avg_stdev" => [],
    ];
    $snotes = $ws["snotes"];
    foreach ($snotes as $cnotes) {
      if ($cnotes !== null) {
        [$min, $max, $avg] = bcnumber::min_max_avg($cnotes, true);
        $stdev = bcnumber::stdev($cnotes, true);
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
    self::add_stat_brow("Note min", $stats["notes_min"], $Spv["footer"], $Spv["footer_cols_styles"]);
    self::add_stat_brow("Note max", $stats["notes_max"], $Spv["footer"], $Spv["footer_cols_styles"]);
    self::add_stat_brow("Note moy", $stats["notes_avg"], $Spv["footer"], $Spv["footer_cols_styles"]);
    self::add_stat_brow("écart-type", $stats["stdev"], $Spv["footer"], $Spv["footer_cols_styles"]);
    self::add_stat_brow("moy - écart-type", $stats["avg_stdev"], $Spv["footer"], $Spv["footer_cols_styles"]);

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

  protected function writeRows(?PvData $pvData=null): void {
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
    $builder->setDifferentOddEven(true);
    $section_colsStyles = $Spv["body_cols_styles"] ?? null;
    $section_rowStyles = $Spv["body_row_styles"] ?? null;
    foreach ($Spv["body"] as $key => $row) {
      $colsStyle = $section_colsStyles[$key] ?? null;
      if ($colsStyle !== null) $colsStyle = cl::merge($prefix, $colsStyle);
      $rowStyle = $section_rowStyles[$key] ?? null;
      $builder->write(cl::merge($prefix, $row), $colsStyle, $rowStyle);
    }
    $builder->setDifferentOddEven(false);
    $section_colsStyles = $Spv["footer_cols_styles"] ?? null;
    $section_rowStyles = $Spv["footer_row_styles"] ?? null;
    $builder->write([]);
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
      $this->form = new FormBasic([
        "method" => "post",
        "schema" => [
          "sess" => ["array", [], "Sessions"],
          "cols" => ["array", [], "Colonnes"],
          "order" => ["string", null, "Ordre"],
          "xe" => ["bool", null, "Exclure les objets pour lesquels il n'y a ni note ni résultat"],
          "objs" => ["array", [], "Objets à inclure dans l'édition"],
        ],
        "params" => [
          "convert" => ["control" => "hidden", "value" => 1],
          "sess" => false,
          "cols" => false,
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
    return true;
  }

  function printForm(): void {
    $form = $this->getForm();
    $form->printAlert();
    $form->printStart();
    $form->printControl("convert");
    vo::sdiv(["class" => "form-group"]);
    vo::tag("label", [
      "class" => ["control-label", $form->FGL_CLASS()],
      "Sessions à inclure dans l'édition",
    ]);
    vo::sdiv(["class" => $form->FGC_CLASS()]);
    $index = 1;
    foreach ($this->getSessions() as [$ises, $session]) {
      $form->printCheckbox($session, "sess[]", $ises, true, [
        "id" => "ses$index",
        "naked" => true,
        "naked_label" => true,
      ]);
      $index++;
    }
    vo::ediv();
    vo::ediv();
    vo::sdiv(["class" => "form-group"]);
    vo::tag("label", [
      "class" => ["control-label", $form->FGL_CLASS()],
      "Colonnes à inclure dans l'édition",
    ]);
    vo::sdiv(["class" => $form->FGC_CLASS()]);
    $index = 1;
    foreach ($this->getCols() as [$icol, $label, $checked, $ref]) {
      $form->printCheckbox($label, "cols[]", $icol, $checked, [
        "id" => "col$index",
        "naked" => true,
        "naked_label" => true,
      ]);
      $index++;
    }
    vo::ediv();
    vo::ediv();
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
    vo::div(["class" => $form->FGL_CLASS()]);
    vo::sdiv(["class" => $form->FGC_CLASS()]);
    $breaker = new Breaker();
    $gptChildren = false;
    foreach ($this->getSelectableObjs() as $obj) {
      $iobj = $obj["iobj"];
      $gptTitle = $obj["gpt_title"];
      if ($gptChildren && $gptTitle === null) $gptChildren = false;
      if ($gptChildren) {
        if ($breaker->shouldBreakOn($gptTitle)) {
          vo::p([
            "class" => "indent1",
            v::b($gptTitle),
          ]);
        }
        vo::sdiv(["class" => "indent2"]);
      }
      $form->printCheckbox("Inclure {$obj["title"]}", "objs[]", $iobj, true, [
        "naked" => true,
        "naked_label" => true,
      ]);
      if ($gptChildren) vo::ediv();
      if ($obj["gpt_parent"]) $gptChildren = true;
    }
    vo::ediv();
    vo::ediv();
    $sm->printEnd();

    $form->printControl("");
    $form->printEnd();
  }

  function doFormAction(?array $params=null): void {
    $form = $this->getForm();
    $this->setIses($form["sess"]);
    $this->setIcols($form["cols"]);
    $this->setOrder($form["order"]);
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

  function print(?array $params=null): void {
  }
}

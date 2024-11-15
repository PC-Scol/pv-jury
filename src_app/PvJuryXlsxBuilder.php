<?php
namespace app;

use nur\sery\ext\spreadsheet\SsBuilder;
use nur\sery\file;
use nur\sery\file\csv\IBuilder;

use nur\sery\file\TempStream;
use nur\sery\file\TmpfileWriter;
use nur\sery\os\path;
use nur\sery\ref\web\ref_mimetypes;
use nur\sery\web\http;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;


//Créer un object avec les styles de cellule
class CellStyles
{
  public $font;
  public $bold;
  public $italic;
  public $underline;
  public $size;
  public $color;
  public $bordertop;
  public $borderbottom;
  public $borderleft;
  public $borderright;
  public $formatcell;

  public function __construct($font, $bold = false, $italic = false, $underline = false, $size = 11, $color = null, $bordertop = null, $borderbottom = null, $borderleft = null, $borderright = null, $formatcell = null)
  {
    $this->font = $font;
    $this->bold = $bold;
    $this->italic = $italic;
    $this->underline = $underline;
    $this->size = $size;
    $this->color = $color;
    $this->bordertop = $bordertop;
    $this->borderbottom = $borderbottom;
    $this->borderleft = $borderleft;
    $this->borderright = $borderright;
    $this->formatcell = $formatcell;
  }
}


class PvJuryXlsxBuilder
{
  private ?IBuilder $builder = null;

  private ?Spreadsheet $spreadsheet = null;

  private $output = null;
  //chemin vers ficher ods modele

  private $xlsxModel = __DIR__."/../config/resources/modele-pv-de-jury.xlsx";


  private function getCellStylesFromModel($spreadsheet_model, $titles, $colonne = 'A', $ligne = 1): array
  {
    $cellStyles = array();


    for ($i = 0; $i < count($titles); $i++) {
      $temp = clone $spreadsheet_model->getActiveSheet()->getStyle($colonne . ($i + $ligne));
      $cellStyles[$i] = new CellStyles(
        $temp->getFont()->getName(),
        $temp->getFont()->getBold(),
        $temp->getFont()->getItalic(),
        $temp->getFont()->getUnderline(),
        $temp->getFont()->getSize(),
        $temp->getFont()->getColor()->getRGB(),
        $temp->getBorders()->getTop()->getBorderStyle(),
        $temp->getBorders()->getBottom()->getBorderStyle(),
        $temp->getBorders()->getLeft()->getBorderStyle(),
        $temp->getBorders()->getRight()->getBorderStyle()

      );
    }

    return $cellStyles;
  }

  private function apply_style(CellStyles $cellStyles, $cell): void
  {
    $cell->getFont()->setName($cellStyles->font);
    $cell->getFont()->setBold($cellStyles->bold);
    $cell->getFont()->setItalic($cellStyles->italic);
    $cell->getFont()->setUnderline($cellStyles->underline);
    $cell->getFont()->setSize($cellStyles->size);
    $cell->getFont()->getColor()->setRGB($cellStyles->color);
    $cell->getBorders()->getTop()->setBorderStyle($cellStyles->bordertop);
    $cell->getBorders()->getBottom()->setBorderStyle($cellStyles->borderbottom);
    $cell->getBorders()->getLeft()->setBorderStyle($cellStyles->borderleft);
    $cell->getBorders()->getRight()->setBorderStyle($cellStyles->borderright);
    if ($cellStyles->formatcell != null) {
        $cell->getStyle()->getNumberFormat()->setFormatCode($cellStyles->formatcell);
    }
  }

  private function countSpaces($array): array
  {
    $result = [];
    $count = 0;
    $foundFirstNonEmpty = false;

    foreach ($array as $item) {
      if (empty($item)) {
        $count++;
      } else {
        if ($foundFirstNonEmpty) {
          $result[] = $count;
        } else {
          if ($count > 0) {
            $result[] = $count;
          }
          $foundFirstNonEmpty = true;
        }
        $count = 0;
      }
    }


    if ($count > 0 && $foundFirstNonEmpty) {
      $result[] = $count;
    }

    return $result;
  }

  function config_print($sheet, $data)
  {
    // Set orientation to landscape
    $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);

    // Set paper size to A3
    $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A3);

    // Set minimal margins
    $sheet->getPageMargins()->setTop(0.5);
    $sheet->getPageMargins()->setRight(0.5);
    $sheet->getPageMargins()->setLeft(0.5);
    $sheet->getPageMargins()->setBottom(0.5);

    // Repeat rows 8 and 9 at the top of each page
    $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(8, 9);

    // **Repeat column 'A' at the left of each page**
    $sheet->getPageSetup()->setColumnsToRepeatAtLeftByStartAndEnd('A', 'A');

    // **Set the print area dynamically based on the used range**
    $highestColumn = $sheet->getHighestColumn();
    $highestRow = $sheet->getHighestRow();
    $printArea = 'A1:' . $highestColumn . $highestRow;
    $sheet->getPageSetup()->setPrintArea($printArea);

    return $sheet;
  }

  function identifySections($headers_row)
  {
    $sections = []; // Each section: ['start' => int, 'end' => int, 'name' => string]

    $currentSection = null;
    $startIndex = null;

    for ($i = 0; $i < count($headers_row); $i++) {
      $headerValue = $headers_row[$i];
      if (!empty($headerValue)) {
        // New section starts
        if ($currentSection !== null) {
          // Close the previous section
          $sections[] = [
            'start' => $startIndex,
            'end' => $i - 1,
            'name' => $currentSection,
          ];
        }
        $currentSection = $headerValue;
        $startIndex = $i;
      }
    }

    if ($currentSection !== null) {
      // Close the last section
      $sections[] = [
        'start' => $startIndex,
        'end' => count($headers_row) - 1,
        'name' => $currentSection,
      ];
    }

    return $sections;
  }

  function inverse_order_array($array)
  {
    $result = [];
    for ($i = count($array) - 1; $i >= 0; $i--) {
      $result[] = $array[$i];
    }
    return $result;
  }


  function build(array $data, $output): self
  {
    $this->output = $output;

    $this->spreadsheet = new Spreadsheet();
    $sheet = $this->spreadsheet->getActiveSheet();
    //Renommer l'onglet en "Promo"
    $sheet->setTitle("Promo");



    // Importer le modèle xlsx
    $spreadsheet_model = IOFactory::load($this->xlsxModel);

    // Prendre la feuille "Promo"
    $spreadsheet_model->setActiveSheetIndexByName("Promo");


    // Obtenir les styles de cellule pour le titre
    $cellStyles_title = $this->getCellStylesFromModel($spreadsheet_model, $data["document"]["title"]);

    //Ecrire le title de data dans le fichier excel
    for ($i = 0; $i < count($data["document"]["title"]); $i++) {
      // Écrire la valeur dans la cellule
      $sheet->setCellValue('A' . ($i + 1), $data["document"]["title"][$i]);
      // Appliquer le style de la cellule
      $this->apply_style($cellStyles_title[$i], $sheet->getStyle('A' . ($i + 1)));

      //hauteur de la ligne en fonction de la taille de la police
      $sheet->getRowDimension($i + 1)->setRowHeight($cellStyles_title[$i]->size * 1.5);
    }



    //getCellStylesFromModel pour le Style header
    $cellStyles_header = $this->getCellStylesFromModel($spreadsheet_model, [$data["document"]["header"]], 'D', 7);



    //Ecrire le document header dans le fichier excel
    $sheet->setCellValue('C' . (count($data["document"]["title"]) + 3), $data["document"]["header"]);
    $this->apply_style($cellStyles_header[0], $sheet->getStyle('D' . (count($data["document"]["title"]) + 3)));

    //style header_pre
    //style header_ecrit
    //style header post
    //style header_fin

    $counter_name = $this->countSpaces($data["promo"]["headers"][0]);

    //print_r($this->identifySections($data["promo"]["headers"][0]));

    // Récupérer le style de la ligne 8 du modèle pour [promo][headers][0]
    $cellStyle_A8_pre = $this->getCellStylesFromModel($spreadsheet_model, ['A8'], 'A', 8)[0];
    $cellStyle_A8_ecrit = $this->getCellStylesFromModel($spreadsheet_model, ['E8'], 'E', 8)[0];
    $cellStyle_A8_post = $this->getCellStylesFromModel($spreadsheet_model, ['F8'], 'F', 8)[0];
    $cellStyle_A8_fin = $this->getCellStylesFromModel($spreadsheet_model, ['J8'], 'J', 8)[0];
    // Récupérer le style de la ligne 9 du modèle pour [promo][headers][1]
    $cellStyle_A9 = $this->getCellStylesFromModel($spreadsheet_model, ['A9'], 'A', 9)[0];

    //Ecrire le header de data dans le fichier excel
    for ($i = 0; $i < count($data["promo"]["headers"]); $i++) {
      $headerRow = $data["promo"]["headers"][$i];
      for ($j = 0; $j < count($headerRow); $j++) {
        $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($j + 1);
        $sheet->setCellValue($columnLetter . ($i + 4 + count($data["document"]["title"])), $headerRow[$j]);
      }
    }

    //Appliquer le style cellStyle_A8_pre pour les premières $counter_name[0] colonnes
    for ($i = 0; $i < $counter_name[0]; $i++) {
      $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
      $this->apply_style($cellStyle_A8_pre, $sheet->getStyle($columnLetter . (count($data["document"]["title"]) + 4)));
    }
    $nb_colonnes_precedentes = $counter_name[0];
    for ($i = 1; $i < count($counter_name); $i++) {
      //Appliquer le style cellStyle_A8_post entre les colonnes
      for ($j = 0; $j < $counter_name[$i]; $j++) {
        $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + $nb_colonnes_precedentes + $j);
        $this->apply_style($cellStyle_A8_post, $sheet->getStyle($columnLetter . (count($data["document"]["title"]) + 4)));
      }
      //Appliquer le style cellStyle_A8_ecrit
      $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + $nb_colonnes_precedentes);
      $this->apply_style($cellStyle_A8_ecrit, $sheet->getStyle($columnLetter . (count($data["document"]["title"]) + 4)));

      $nb_colonnes_precedentes += $counter_name[$i];
      $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + $nb_colonnes_precedentes);
      $this->apply_style($cellStyle_A8_fin, $sheet->getStyle($columnLetter . (count($data["document"]["title"]) + 4)));
    }

    //Appliquer le style cellStyle_A9 pour la ligne 9
    for ($i = 0; $i < count($data["promo"]["headers"][1]); $i++) {
      $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
      $this->apply_style($cellStyle_A9, $sheet->getStyle($columnLetter . (count($data["document"]["title"]) + 5)));
    }

    $width = count($data["promo"]["body"][1]);

    //Recupérer le style de body dans le modèle
    $cellStyle_etudiant_name = $this->getCellStylesFromModel($spreadsheet_model, ['A10'], 'A', 10)[0];
    $cellStyle_etudiant_milieu = $this->getCellStylesFromModel($spreadsheet_model, ['A11'], 'A', 11)[0];
    $cellStyle_etudiant_fin = $this->getCellStylesFromModel($spreadsheet_model, ['A12'], 'A', 12)[0];
    $cellStyle_etudiant_milieu_droite = $this->getCellStylesFromModel($spreadsheet_model, ['B10'], 'B', 10)[0];
    $cellStyle_etudiant_fin_droite = $this->getCellStylesFromModel($spreadsheet_model, ['J10'], 'J', 10)[0];

    //Si dans la config, il y a groupement a 1, regrouper les styles de groupement dans le modèle
    if ($data["config"]['have_gpt'] == 1) {
      $cellStyle_Gpt_name = $this->getCellStylesFromModel($spreadsheet_model, ['B11'], 'B', 11)[0];
      $cellStyle_Gpt_milieu = $this->getCellStylesFromModel($spreadsheet_model, ['B12'], 'B', 12)[0];
      $cellStyle_Gpt_fin = $this->getCellStylesFromModel($spreadsheet_model, ['B13'], 'B', 13)[0];
    }

    //Recupérer Style body dans le modèle
    $cell_Style_body = $this->getCellStylesFromModel($spreadsheet_model, ['C11'], 'C', 11)[0];

    $nom_ligne_taille = array(); // array avec [nom, position,taille]
    //Ecrire le body de data dans le fichier excel
    for ($i = 0; $i < count($data["promo"]["body"]); $i++) {
      $bodyRow = $data["promo"]["body"][$i];
      for ($j = 0; $j < count($bodyRow); $j++) {
        $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($j + 1);
        $sheet->setCellValue($columnLetter . ($i + 4 + count($data["document"]["title"]) + count($data["promo"]["headers"])), $bodyRow[$j]);
        //si ["promo"]["body"][$i][0] est non vide, on ajoute ["promo"]["body"][$i][0] dans $nom_ligne_taille
        //la taille est l'ecart entre deux noms et si c'est le dernier nom, la taille est jusqu'à la fin de la ligne
        if ($j == 0 && !empty($bodyRow[$j])) {
          $nom_ligne_taille[] = [$bodyRow[$j], $i + 4 + count($data["document"]["title"]) + count($data["promo"]["headers"]), 0];
        }
      }
    }

    //Calculer la taille de chaque nom
    for ($i = 0; $i < count($nom_ligne_taille); $i++) {
      if ($i == count($nom_ligne_taille) - 1) {
        $nom_ligne_taille[$i][2] = count($data["promo"]["body"]) - $nom_ligne_taille[$i][1] + 4 + count($data["document"]["title"]) + count($data["promo"]["headers"]);
      } else {
        $nom_ligne_taille[$i][2] = $nom_ligne_taille[$i + 1][1] - $nom_ligne_taille[$i][1];
      }
    }

    //Appliquer le style cell_Style_body pour tout le body
    for ($i = 0; $i < count($data["promo"]["body"]); $i++) {
      for ($j = 1; $j < $width; $j++) {
        $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($j + 1);
        $this->apply_style($cell_Style_body, $sheet->getStyle($columnLetter . ($i + 4 + count($data["document"]["title"]) + count($data["promo"]["headers"]))));
      }
    }

    //Appliquer le style cellStyle_etudiant_name pour les noms, cellStyle_etudiant_milieu pour les lignes entre les noms et cellStyle_etudiant_fin pour la dernière ligne
    for ($i = 0; $i < count($nom_ligne_taille); $i++) {
      $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(1);
      $this->apply_style($cellStyle_etudiant_name, $sheet->getStyle($columnLetter . $nom_ligne_taille[$i][1]));
      for ($j = 1; $j < $nom_ligne_taille[$i][2]; $j++) {
        $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(1);
        $this->apply_style($cellStyle_etudiant_milieu, $sheet->getStyle($columnLetter . ($nom_ligne_taille[$i][1] + $j)));
      }
      $this->apply_style($cellStyle_etudiant_fin, $sheet->getStyle($columnLetter . ($nom_ligne_taille[$i][1] + $nom_ligne_taille[$i][2] - 1)));
    }

    //Appliquer le style cellStyle_etudiant_milieu_droit et cellStyle_etudiant_fin_droit pour chaque nom sur toute la ligne
    for ($i = 0; $i < count($nom_ligne_taille); $i++) {
      for ($j = 1; $j < $width; $j++) {
        $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($j + 1);
        $this->apply_style($cellStyle_etudiant_milieu_droite, $sheet->getStyle($columnLetter . $nom_ligne_taille[$i][1]));
      }
      //Appliquer le style cellStyle_etudiant_fin_droit pour la dernière colonne
      $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($width);
      $this->apply_style($cellStyle_etudiant_fin_droite, $sheet->getStyle($columnLetter . $nom_ligne_taille[$i][1]));
    }

    //Recuperer la longueur de la colonne A
    $row_A_length = $spreadsheet_model->getActiveSheet()->getColumnDimensions()['A']->getWidth("cm");
    $sheet->getColumnDimension('A')->setWidth($row_A_length - 0.51, "cm");

    //Recuperer la longueur de la colonne B
    $row_B_length = $spreadsheet_model->getActiveSheet()->getColumnDimensions()['B']->getWidth("cm");
    $sheet->getColumnDimension('B')->setWidth($row_B_length - 0.51, "cm");

    //Recuperer la longueur de la colonne C
    $row_C_length = $spreadsheet_model->getActiveSheet()->getColumnDimensions()['C']->getWidth("cm");
    $sheet->getColumnDimension('C')->setWidth($row_C_length - 0.51, "cm");

    //Recuperer la longueur de la colonne D
    $row_D_length = $spreadsheet_model->getActiveSheet()->getColumnDimensions()['D']->getWidth("cm");
    $sheet->getColumnDimension('D')->setWidth($row_D_length - 0.51, "cm");


    //config print
    $sheet = $this->config_print($sheet, $data);


    //Créer une nouvelle feuille stat
    $sheet = $this->spreadsheet->createSheet();
    $sheet->setTitle("Stats");

    for ($i = 0; $i < count($data["document"]["title"]); $i++) {
      // Écrire la valeur dans la cellule
      $sheet->setCellValue('A' . ($i + 1), $data["document"]["title"][$i]);
      // Appliquer le style de la cellule
      $this->apply_style($cellStyles_title[$i], $sheet->getStyle('A' . ($i + 1)));

      //hauteur de la ligne en fonction de la taille de la police
      $sheet->getRowDimension($i + 1)->setRowHeight($cellStyles_title[$i]->size * 1.5);
    }



    //Basculer sur stats pour le modele
    $spreadsheet_model->setActiveSheetIndexByName("Stats");

    //recuperer les styles de cellule pour le titre
    $cellStyles_title_notes = $this->getCellStylesFromModel($spreadsheet_model, [0],"B",6);
    //Recuperer le style de la cellule B8
    $cellStyle_B8 = $this->getCellStylesFromModel($spreadsheet_model, ['B8'], 'B', 8);


    //Ecrire $data["stats"]["headers"] dans le fichier excel
    for ($i = 0; $i < count($data["stats"]["headers"]); $i++) {
      $headerRow = $data["stats"]["headers"][$i];
      for ($j = 0; $j < count($headerRow); $j++) {
      $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($j + 1);
      $sheet->setCellValue($columnLetter . ($i + 6), $headerRow[$j]); // Écrire 5 cellules plus bas
      }
    }

    //Appliquer le style sur la cellule B7 si pas de groupement et C7 si groupement
    if ($data["config"]['have_gpt'] == 1) {
      $this->apply_style($cellStyles_title_notes[0], $sheet->getStyle('C7'));
    } else {
      $this->apply_style($cellStyles_title_notes[0], $sheet->getStyle('B7'));
    }

    //Recuperer le style du modele A7
    $cellStyle_A7 = $this->getCellStylesFromModel($spreadsheet_model, ['A7'], 'A', 7)[0];
    //Appliquer le style cellStyle_A7 pour la ligne 8
    for ($i = 0; $i < count($data["stats"]["headers"][0]); $i++) {
      $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
      $this->apply_style($cellStyle_A7, $sheet->getStyle($columnLetter . 8));
    }

    $longueur_max=0;

    //Ecire $data["stats"]["body"] dans le fichier excel
    for ($i = 0; $i < count($data["stats"]["body"]); $i++) {
      $bodyRow = $data["stats"]["body"][$i];
      if (count($bodyRow) > $longueur_max) {
        $longueur_max = count($bodyRow);
      }
      for ($j = 0; $j < count($bodyRow); $j++) {
      $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($j + 1);
      $sheet->setCellValue($columnLetter . ($i + 9), $bodyRow[$j]);
      }
    }

    //Apliquer le style cellStyle_B8 sur toute les cellules ecrite via $data["stats"]["body"]
    for($i=0;$i<count($data["stats"]["body"]);$i++){
      for($j=0;$j<$longueur_max;$j++){
        $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($j + 1);
        $this->apply_style($cellStyle_B8[0], $sheet->getStyle($columnLetter . ($i + 9)));
      }
    }


    // Déterminer la colonne de départ en fonction de la configuration
    $startColumn = $data["config"]['have_gpt'] == 1 ? 'B' : 'A';


    //Ecrire $data["totals"][headers] en dessous de $data["stats"]["body"]
    for ($i = 0; $i < count($data["totals"]["headers"]); $i++) {
      $headerRow = $data["totals"]["headers"][$i];
      for ($j = 0; $j < count($headerRow); $j++) {
      $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($j + \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($startColumn));
      $cell = $sheet->setCellValue($columnLetter . ($i + 10 + count($data["stats"]["body"])), $headerRow[$j]);
      // Ajouter des bordures
      $sheet->getStyle($columnLetter . ($i + 10 + count($data["stats"]["body"])))
          ->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
      }
    }

    // Écrire $data["totals"]["body"] en dessous de $data["totals"]["headers"]
    for ($i = 0; $i < count($data["totals"]["body"]); $i++) {
      $bodyRow = $data["totals"]["body"][$i];
      for ($j = 0; $j < count($bodyRow); $j++) {
      $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($j + \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($startColumn));
      $cell = $sheet->setCellValue($columnLetter . ($i + 11 + count($data["stats"]["body"])), $bodyRow[$j]);
      // Ajouter des bordures
      $sheet->getStyle($columnLetter . ($i + 11 + count($data["stats"]["body"])))
        ->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

      // Mettre les cellules de la colonne pourcentage en pourcentage
      if ($data["totals"]["headers"][0][$j] === 'Pourcentage') {
        $sheet->getStyle($columnLetter . ($i + 11 + count($data["stats"]["body"])))
          ->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00);
      }
      }
    }

    return $this;
  }

  function write(): void
  {
    $writer = new Xlsx($this->spreadsheet);
    $writer->save($this->output);


    $this->builder = null;
    $this->output = null;
  }

  function send(bool $exit = true): void
  {
    $tmpfile = new TmpfileWriter();
    $writer = new Xlsx($this->spreadsheet);
    $writer->save($tmpfile->getFile());

    $output = $this->output;
    if ($output === null) {
      $output = "pv-de-jury.xlsx";
    } else {
      $output = path::filename($output);
    }
    http::content_type(ref_mimetypes::XLSX);
    http::download_as($output);

    $tmpfile->fpassthru();
    if ($exit)
      exit();
  }
}

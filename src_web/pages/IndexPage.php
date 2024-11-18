<?php
namespace web\pages;

use app\PvDataExtractor;
use app\PvJuryXlsxBuilder;
use app\pvs;
use Exception;
use nur\sery\app;
use nur\sery\cl;
use nur\sery\file;
use nur\sery\file\web\Upload;
use nur\sery\os\path;
use nur\sery\web\uploads;
use nur\v\al;
use nur\v\bs3\Bs3IconManager;
use nur\v\bs3\fo\Form;
use nur\v\bs3\fo\FormInline;
use nur\v\bs3\plugins\formfilePlugin;
use nur\v\bs3\vc\CTable;
use nur\v\icon;
use nur\v\ly;
use nur\v\page;
use nur\v\v;
use nur\v\vo;
use web\init\ANavigablePage;

class IndexPage extends ANavigablePage {
  const TITLE = "PV Jury";

  function setup(): void {
    $importfo = $this->importfo = new FormInline([
      "upload" => true,
      "params" => [
        "import" => ["control" => "hidden", "value" => 1],
        "action" => ["control" => "hidden", "value" => "import"],
        "file" => ["control" => "file",
          "label" => [],
          "btn_label" => "Importer un fichier",
          "accept" => ".csv",
          "accesskey" => "q",
        ],
      ],
      "autoadd_submit" => false,
      "submitted_key" => "import",
      "autoload_params" => true,
    ]);
    $this->addPlugin(new formfilePlugin("Importation de '", "'...", formfilePlugin::AUTOSUBMIT_ON_CHANGE));

    if ($importfo->isSubmitted()) {
      al::reset();
      try {
        /** @var Upload[] $files */
        $this->file = uploads::get("file");
      } catch (Exception $e) {
        $this->dispatchAction(false);
        al::error($e->getMessage());
      }
    } else {
      $this->pvs = cl::all(pvs::channel()->all(null, [
        "cols" => ["name", "title", "date", "lib_usr"],
        "order by" => "date desc, name",
        "suffix" => "limit 100",
      ]));
    }
  }

  /** @var Form */
  protected $importfo;

  /** @var Upload */
  protected $file;

  /** @var array */
  protected $pvs;

  const VALID_ACTIONS = ["import"];
  const ACTION_PARAM = "action";

  function importAction() {
    $file = $this->file;
    pvs::channel()->charge($file, null, null, $values);
    page::redirect(page::bu(ConvertPage::class, ["n" => $values["name"]]));
  }

  function print(): void {
    ly::row();
    ly::col(12);
    vo::h1(self::TITLE);
    vo::p("Veuillez déposer le fichier édité depuis PEGASE. Les options seront affichées une fois le fichier importé");

    al::print();
    $this->importfo->print();

    $pvs = $this->pvs;
    if ($pvs) {
      ly::row(["class" => "gap-row"]);
      ly::col(12);
      vo::p("Vous pouvez aussi sélectionner un PV dans la liste des fichiers qui ont déjà été importés");
      new CTable($pvs, [
        "table_class" => "table-bordered table-auto",
        "cols" => ["name", "title", "date", "lib_usr", null],
        "headers" => ["Nom", "Type", "Date édition", "Importé par", "Action"],
        "col_func" => function($vs, $value, $col, $index, $row) {
          $icons = icon::manager();
          $name = $row["name"];
          if ($col === "name") {
            return v::a($icons->getIcon("print", $name), page::bu(ConvertPage::class, ["n" => $name]));
          } elseif ($col === null) {
            return [
              v::a("Editer PV", page::bu(ConvertPage::class, ["n" => $name])),
              "&nbsp;&nbsp;|&nbsp;&nbsp;",
              v::a($icons->getIcon("eye-open", "Consulter"), page::bu(ViewPage::class, ["n" => $name]))
            ];
          }
          return $vs;
        },
        "autoprint" => true,
      ]);
    }

    ly::end();
  }
}

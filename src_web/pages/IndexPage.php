<?php
namespace web\pages;

use app\pvs;
use Exception;
use nur\authz;
use nur\sery\cl;
use nur\sery\file\web\Upload;
use nur\sery\web\uploads;
use nur\v\al;
use nur\v\bs3\fo\Form;
use nur\v\bs3\fo\FormInline;
use nur\v\bs3\plugins\formfilePlugin;
use nur\v\bs3\vc\CTable;
use nur\v\icon;
use nur\v\ly;
use nur\v\page;
use nur\v\v;
use nur\v\vo;
use web\init\APvPage;

class IndexPage extends APvPage {
  const TITLE = "PV Jury";
  const AUTOLOAD_PV_DATA = false;

  function setup(): void {
    parent::setup();

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
      $codUsr = authz::get()->getUsername();
      $this->pvs = cl::all(pvs::channel()->all(null, [
        "cols" => [
          "*",
          "mine" => "iif(cod_usr = '$codUsr', 1, 0)",
        ],
        "order by" => "mine desc, date desc, name",
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

  const VALID_ACTIONS = ["import", "download"];
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
        "cols" => ["name", null, "title", "date", "lib_usr"],
        "headers" => ["Nom", "Action", "Type", "Date édition", "Importé par"],
        "col_func" => function($vs, $value, $col, $index, $row) {
          $icons = icon::manager();
          $name = $row["name"];
          if ($col === "name") {
            return v::a($icons->getIcon("print", $row["origname"]), page::bu(ConvertPage::class, ["n" => $name]));
          } elseif ($col === null) {
            return [
              v::a($icons->getIcon("eye-open", "Afficher"), page::bu(ViewPage::class, ["n" => $name])),
              "&nbsp;&nbsp;|&nbsp;&nbsp;",
              v::a($icons->getIcon("download", "Télécharger"), page::bu("", [
                "action" => "download",
                "n" => $name,
              ])),
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

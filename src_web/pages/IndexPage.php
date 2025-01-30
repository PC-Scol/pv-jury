<?php
namespace web\pages;

use app\pvs;
use Exception;
use nur\authz;
use nulib\cl;
use nulib\file\web\Upload;
use nulib\web\uploads;
use nur\v\al;
use nur\v\bs3\fo\Form;
use nur\v\bs3\fo\FormInline;
use nur\v\bs3\plugins\formfilePlugin;
use nur\v\bs3\vc\CTable;
use nur\v\icon;
use nur\v\ly;
use nur\v\page;
use nur\v\plugins\autosubmitSelectPlugin;
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
      $pvChannel = pvs::channel();
      $codUsr = authz::get()->getUsername();
      $mineCol = "iif(cod_usr = '$codUsr', 0, 1)";

      $usrs = cl::all($pvChannel->getCapacitor()->db()->all([
        "select distinct",
        "cols" => [
          "cod_usr" => "coalesce(cod_usr, '')",
          "lib_usr",
          "mine" => $mineCol,
        ],
        "from" => $pvChannel->getTableName(),
        "order by" => "mine, lib_usr",
      ]));
      $usrfo = $this->usrfo = new FormInline([
        "schema" => [
          "sel_usr" => ["?string"],
        ],
        "params" => [
          "sel_usr" => [
            "control" => "select",
            "label" => "Afficher les fichiers importés par",
            "items" => $usrs,
            "item_value_key" => "cod_usr",
            "item_text_key" => "lib_usr",
            "default" => $codUsr,
          ],
        ],
        "autoadd_submit" => false,
        "autoload_params" => true,
      ]);
      $this->addPlugin(new autosubmitSelectPlugin("#sel_usr"));
      $selUsr = $usrfo->get("sel_usr", false);

      $this->pvs = cl::all($pvChannel->all(null, [
        "cols" => [
          "*",
          "mine" => $mineCol,
        ],
        "where" => [
          "cod_usr" => $selUsr,
        ],
        "order by" => "mine, date desc, name",
      ]));
    }
  }

  /** @var Form */
  protected $importfo;

  /** @var Upload */
  protected $file;

  /** @var Form */
  protected $usrfo;

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
      $this->usrfo->print();
      new CTable($pvs, [
        "table_class" => "table-bordered table-auto",
        "cols" => ["name", null, "title", "date"],
        "headers" => ["Nom", "Action", "Type", "Date édition"],
        "col_func" => function($vs, $value, $col, $index, $row) {
          $icons = icon::manager();
          $name = $row["name"];
          if ($col === "name") {
            return v::a($icons->getIcon("print", $row["origname"]), page::bu(ConvertPage::class, ["n" => $name]));
          } elseif ($col === null) {
            return [
              v::a([
                "href" => page::bu(ViewPage::class, ["n" => $name]),
                "target" => "_blank",
                $icons->getIcon("eye-open", "Afficher")
              ]),
              "&nbsp;&nbsp;|&nbsp;&nbsp;",
              v::a([
                "href" => page::bu("", [
                  "action" => "download",
                  "n" => $name,
                ]),
                $icons->getIcon("download", "Télécharger")
              ]),
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

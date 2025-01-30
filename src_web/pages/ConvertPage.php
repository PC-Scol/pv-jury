<?php
namespace web\pages;

use app\CsvPvModel1Builder;
use Exception;
use nulib\cl;
use nulib\os\path;
use nulib\text\words;
use nur\v\al;
use nur\v\bs3\fo\Form;
use nur\v\bs3\fo\FormBasic;
use nur\v\icon;
use nur\v\page;
use nur\v\v;
use nur\v\vo;
use web\init\APvPage;

class ConvertPage extends APvPage {
  const TITLE = "PV Jury";

  function setup(): void {
    parent::setup();
    $pvData = $this->pvData;
    $this->count = count($pvData->rows);

    $builder = $this->builder = new CsvPvModel1Builder($pvData);
    $sessions = $this->sessions = $builder->getSessions();
    $convertfo = $this->convertfo = new FormBasic([
      "method" => "post",
      "schema" => [
        "ises" => ["?int", null, "Session"],
        "order" => ["string", null, "Ordre"],
        "xc" => ["bool", null, "NE PAS inclure les controles"],
        "xe" => ["bool", null, "NE PAS inclure les éléments pour lesquels il n'y a ni note ni résultat"],
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
            [CsvPvModel1Builder::ORDER_MERITE, "Classer par mérite (note)"],
            [CsvPvModel1Builder::ORDER_ALPHA, "Classer par ordre alphabétique (nom)"],
          ],
        ],
        "xe" => [
          "control" => "checkbox",
          "value" => 1,
        ],
      ],
      "submit" => [
        "Editer le PV",
        "name" => "action",
        "value" => "convert",
        "accesskey" => "s",
      ],
      "submitted_key" => "convert",
      "autoload_params" => true,
    ]);
    $action = false;
    if ($convertfo->isSubmitted()) {
      al::reset();
      if ($convertfo["ises"] !== null) {
        $action = true;
      } else {
        al::error("Vous devez choisir la session");
        $this->dispatchAction(false);
      }
    }
  }

  private int $count;

  private array $sessions;

  private CsvPvModel1Builder $builder;

  private Form $convertfo;

  const VALID_ACTIONS = ["download", "convert"];
  const ACTION_PARAM = "action";

  function convertAction() {
    page::more_time();
    $builder = $this->builder;
    $convertfo = $this->convertfo;
    $builder->setIses($convertfo["ises"]);
    $builder->setOrder($convertfo["order"]);
    $builder->setExcludeControles(boolval($convertfo["xc"]));
    $builder->setExcludeUnlessHaveValue(boolval($convertfo["xe"]));
    $suffix = $builder->getSuffix();
    $output = path::filename($this->pvData->origname);
    $output = path::ensure_ext($output, "-$suffix.xlsx", ".csv");
    try {
      $builder->build($output)->send();
    } catch (Exception $e) {
      al::error($e->getMessage());
    }
    page::redirect(true);
  }

  const HAVE_JQUERY = true;

  function printJquery(): void {
    ?>
<script type="text/javascript">
  jQuery.noConflict()(function($) {
    $("#copy-link").click(function() {
      var $this = $(this);
      navigator.clipboard.writeText($("#view-link").attr("href"));
      $this.addClass("btn-success").text("Lien copié!");
      window.setTimeout(function() {
        $this.removeClass("btn-success").text("Copier le lien");
      }, 1000);
      return false;
    });
  });
</script>
<?php
  }
  function print(): void {
    $pvData = $this->pvData;
    $title = array_filter($pvData->title);
    vo::h1([
      $title[0],
      "<br/>",
      v::tag("small", [
        join("<br/>", array_slice($title, 1)),
      ])
    ]);
    vo::p([
      "Cette page permet de gérer l'import du fichier <tt>",
      $pvData->origname,
      "</tt>",
    ]);
    vo::ul([
      v::li([
        "Il y a ",
        words::q($this->count, "l'étudiant#s dans ce fichier|m"),
      ]),
      v::li([
        v::a([
          "href" => page::bu("", [
            "action" => "download",
            "n" => $this->name,
          ]),
          "Télécharger ",
          icon::download("au format Excel"),
        ]),
        " (convertir le fichier CSV au format Excel <em>sans modifications du contenu</em>)"
      ]),
      v::li([
        v::a([
          "id" => "view-link",
          "href" => getenv("BASE_URL").page::bu(ViewPage::class, [
            "n" => $this->name,
          ]),
          "target" => "_blank",
          "Afficher ",
          icon::eye_open("le détail des dossiers étudiants"),
        ]),
        " (consultation en ligne des résultats, par étudiant)",
        "<br/>",
        v::a([
          "id" => "copy-link",
          "class" => "btn btn-default",
          "href" => "#",
          "Copier le lien"
        ]),
        " (pour partager avec les enseignants)",
      ]),
    ]);

    vo::p(["<b>Edition du PV</b> (met en forme les données du fichier CSV pour impression. inclure aussi les statistiques)"]);
    al::print();
    $this->convertfo->print();

    $builder = $this->builder;
    $builder->setExcludeUnlessHaveValue(true);
    foreach ($this->sessions as [$ises, $session]) {
      vo::h2($session);
      $builder->setIses($ises);
      $builder->compute();
      $builder->print();
    }
  }
}

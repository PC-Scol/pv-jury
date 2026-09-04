<?php
namespace web\pages;

use app\PvData;
use app\PvDataExtractor;
use app\PvModelBuilderClassicEdition;
use app\PvModelBuilderDisplay;
use app\PvModelBuilderPegaseEdition;
use nulib\os\path;
use nulib\os\sh;
use nulib\php\types\vbool;
use nulib\php\types\vint;
use nulib\web\params\F;
use nur\v\al;
use nur\v\page;
use nur\v\v;
use nur\v\vo;
use web\init\ANavigablePage;

class TbinDumpExtractPage extends ANavigablePage {
  function setup() {
    al::reset();
    $input = F::get("input");
    if ($input) {
      $input = path::join(__DIR__.'/../../tbin/samples', basename($input));
      if (!file_exists($input)) {
        al::error("input does not exist");
      } else {
        $extractor = new PvDataExtractor();
        $this->pvData = $pvData = $extractor->extract($input);
        $this->type = $type = F::get("type");
        switch ($type) {
        case "c":
          $classic = new PvModelBuilderClassicEdition($pvData);
          $this->cc = $cc = vbool::with(F::get("cc", false));
          $classic->setAddCoeffCol($cc);
          $this->ises = $ises = vint::with(F::get("i", 0));
          $classic->setIses($ises);
          $classic->compute();
          $data = $pvData->ws;
          break;
        case "d":
          $display = new PvModelBuilderDisplay($pvData);
          $display->compute();
          $data = $pvData->ws;
          break;
        case "p":
          $pegase = new PvModelBuilderPegaseEdition($pvData);
          $pegase->compute();
          $data = $pvData->ws;
          break;
        default:
          $data = $pvData->data;
          break;
        }
        $this->addPlugin($this->cdumpData = new CDumpData($data));
      }
    }
  }

  protected ?PvData $pvData = null;

  protected ?string $type = null;

  protected ?bool $cc = null;

  protected ?int $ises = null;

  protected ?CDumpData $cdumpData = null;

  function print(): void {
    al::print();
    if ($this->cdumpData !== null) {
      vo::p([
        v::a("RESET", page::self()),
        ", ",
        v::a("pvData", page::bu("", F::select(null, ["type"]))),
        ", ",
        v::a("classic", page::bu("", F::select(null, null, [
          "type" => "c",
        ]))),
        ", ",
        v::a("display", page::bu("", F::select(null, null, [
          "type" => "d",
        ]))),
        ", ",
        v::a("pegase", page::bu("", F::select(null, null, [
          "type" => "p",
        ]))),
        v::if($this->type === "c", [
          " | ",
          v::if($this->cc, [
            v::a("~cc", page::bu("", F::select(null, ["cc"]))),
          ]),
          v::unless($this->cc, [
            v::a("cc", page::bu("", F::select(null, null, [
              "cc" => 1,
            ]))),
          ]),
          v::foreach($this->pvData->data["ses_cols"], static function($ses, $ises) {
            return [
              ", ",
              v::a([
                "title" => $ses["title"],
                "href" => page::bu("", F::select(null, null, [
                  "i" => $ises,
                ])),
                "i=$ises",
              ]),
            ];
          }),
        ]),
      ]);
      $this->cdumpData->print();
    } else {
      vo::sdiv(["class" => "list-group"]);
      $inputs = sh::ls_files(__DIR__."/../../tbin/samples", "*.csv");
      foreach ($inputs as $input) {
        vo::a([
          "class" => "list-group-item",
          "href" => page::bu("", ["input" => $input]),
          $input
        ]);
      }
      vo::ediv();
    }
  }
}

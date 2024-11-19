<?php
namespace web\init;

use app\PvData;
use app\pvs;
use nur\sery\ext\spreadsheet\SsBuilder;
use nur\sery\os\path;
use nur\sery\web\params\F;
use nur\v\page;
use web\pages\IndexPage;

class APvPage extends ANavigablePage {
  const AUTOLOAD_PV_DATA = true;

  function setup(): void {
    if (static::AUTOLOAD_PV_DATA) {
      $name = F::get("n");
      $data = pvs::json_data($name);
      if ($data === null) page::redirect(IndexPage::class);
      $this->name = $name;
      $this->pvData = new PvData($data);
    }
  }

  protected string $name;

  protected PvData $pvData;

  function downloadAction() {
    $name = F::get("n");
    $data = pvs::json_data($name);
    if ($data === null) page::redirect(true);
    $pvData = new PvData($data);
    $output = path::ensure_ext($pvData->origname, "-pegase.xlsx", ".csv");
    $builder = SsBuilder::with([
      "output" => $output,
      "use_headers" => false,
      "wsparams" => [
        "sheetView_freezeRow" => count($pvData->headers) + 2,
      ],
    ]);
    $writeAll = function ($rows) use ($builder) {
      foreach ($rows as $row) {
        $builder->write($row);
      }
    };
    $writeAll(array_slice($pvData->headers, 0, -1));
    $writeAll([[]]);
    $writeAll(array_slice($pvData->headers, -1));
    $writeAll($pvData->rows);
    $builder->sendFile();
  }
}

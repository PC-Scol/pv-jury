<?php
namespace web\init;

use app\PvData;
use app\pvs;
use nulib\cl;
use nulib\ext\spout\SpoutBuilder;
use nulib\ext\tab\SsBuilder;
use nulib\os\path;
use nulib\web\http;
use nulib\web\params\F;
use nur\v\page;
use OpenSpout\Writer\XLSX\Options\HeaderFooter;
use OpenSpout\Writer\XLSX\Options\PageMargin;
use OpenSpout\Writer\XLSX\Options\PageOrder;
use OpenSpout\Writer\XLSX\Options\PageOrientation;
use OpenSpout\Writer\XLSX\Options\PageSetup;
use OpenSpout\Writer\XLSX\Options\PaperSize;
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
    $format = F::get("format", "xlsx");
    $data = pvs::json_data($name);
    if ($data === null) page::redirect(true);
    if ($format === "xlsx") {
      $pvData = new PvData($data);
      $output = path::ensure_ext($pvData->origname, "-excel.xlsx", ".csv");
      /** @var SpoutBuilder $builder */
      $builder = SsBuilder::with([
        "output" => $output,
        "use_headers" => false,
        "spout" => [
          "->setColumnWidth" => [0.5, 1],
          "->setPageSetup" => [new PageSetup(PageOrientation::LANDSCAPE, PaperSize::A3, null, null, PageOrder::OVER_THEN_DOWN)],
          "->setPageMargin" => [new PageMargin(0.39, 0.39, 0.75, 0.39)],
          "->setHeaderFooter" => [new HeaderFooter(null, htmlspecialchars("&L{$pvData->origname}&RPage &P / &N"))],
        ],
        "sheet_view" => [
          "->setFreezeRow" => count($pvData->headers) + 2,
          "->setFreezeColumn" => "E",
        ],
      ]);
      $writeAll = function ($rows, ?array $prefix=null) use ($builder) {
        foreach ($rows as $row) {
          $builder->write(cl::merge($prefix, $row));
        }
      };
      $prefix = [null];
      $builder->setDifferentOddEven(false);
      $writeAll(array_slice($pvData->headers, 0, -1), $prefix);
      $writeAll([[]]);
      $writeAll(array_slice($pvData->headers, -1), $prefix);
      $builder->setDifferentOddEven(true);
      $writeAll($pvData->rows, $prefix);
      $builder->setDifferentOddEven(false);
      $writeAll([[]]);
      $writeAll($pvData->title);
      $builder->sendFile();
    } elseif ($format === "csv") {
      $filename = $data["origname"];
      http::content_type("text/csv");
      http::download_as($filename);
      readfile(pvs::upload_file($filename));
    } else {
      page::redirect(true);
    }
  }
}

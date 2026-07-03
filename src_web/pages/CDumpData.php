<?php
namespace web\pages;

use nulib\cl;
use nur\v\base\ComponentPrintable;
use nur\v\v;
use nur\v\vo;

class CDumpData extends ComponentPrintable {
  const CSS = "CDumpData.css";

  function __construct($data) {
    $this->data = $data;
  }

  protected mixed $data;

  const HAVE_JQUERY = true;

  function printJquery(): void {
    ?>
<script type="text/javascript">
jQuery.noConflict()(function($) {
  $(".dd-open").on("click", function() {
    let $parent = $(this).closest("li");
    let $container = $parent.children(".dd-container");
    $container.children(".dd-open").addClass("hidden");
    $container.children(".dd-open-all").addClass("hidden");
    $container.children(".dd-close").removeClass("hidden");
    $container.children(".dd-close-all").removeClass("hidden");
    $parent.children(".dd-children").removeClass("hidden");
  });
  $(".dd-open-all").on("click", function() {
    let $parent = $(this).closest("li");
    $parent.find(".dd-open").addClass("hidden");
    $parent.find(".dd-open-all").addClass("hidden");
    $parent.find(".dd-close").removeClass("hidden");
    $parent.find(".dd-close-all").removeClass("hidden");
    $parent.find(".dd-children").removeClass("hidden");
  });
  $(".dd-close").on("click", function() {
    let $parent = $(this).closest("li");
    let $container = $parent.children(".dd-container");
    $container.children(".dd-open").removeClass("hidden");
    $container.children(".dd-open-all").removeClass("hidden");
    $container.children(".dd-close").addClass("hidden");
    $container.children(".dd-close-all").addClass("hidden");
    $parent.children(".dd-children").addClass("hidden");
  });
  $(".dd-close-all").on("click", function() {
    let $parent = $(this).closest("li");
    $parent.find(".dd-open").removeClass("hidden");
    $parent.find(".dd-open-all").removeClass("hidden");
    $parent.find(".dd-close").addClass("hidden");
    $parent.find(".dd-close-all").addClass("hidden");
    $parent.find(".dd-children").addClass("hidden");
  });
  $(".dd-container").hover(function() {
    $container = $(this);
    $container.children(".dd-open-all").removeClass("invisible");
    $container.children(".dd-close-all").removeClass("invisible");
  }, function() {
    $container = $(this);
    $container.children(".dd-open-all").addClass("invisible");
    $container.children(".dd-close-all").addClass("invisible");
  });
});
</script>
<?php
  }

  function print(): void {
    self::print_array(cl::with($this->data));
  }

  static function print_array(?array $array, ?string $class=null): void {
    if ($array === null) return;
    vo::sul(["class" => ["list-group dd", $class]]);
    foreach ($array as $key => $value) {
      if (is_array($value)) {
        vo::sli([
          "class" => "list-group-item",
          v::div([
            "class" => "dd-container",
            v::span([
              "class" => "dd-control dd-open btn btn-xs text-muted",
              "title" => "Déplier",
              "&nbsp;+&nbsp;",
            ]),
            v::span([
              "class" => "dd-control dd-close btn btn-xs text-muted hidden",
              "title" => "Replier",
              "&nbsp;-&nbsp;",
            ]),
            "&nbsp;",
            v::span(["class" => "dd-key", $key]),
            "&nbsp;: ",
            v::span([
              "class" => "dd-control dd-open-all btn btn-xs text-muted invisible",
              "title" => "Déplier tout",
              "&nbsp;++&nbsp;",
            ]),
            v::span([
              "class" => "dd-control dd-close-all btn btn-xs text-muted hidden invisible",
              "title" => "Replier tout",
              "&nbsp;--&nbsp;",
            ]),
          ]),
        ]);
        self::print_array($value, "dd-children hidden");
        vo::eli();
      } else {
        vo::li([
          "class" => "list-group-item",
          v::span(["class" => "dd-key", $key]),
          "&nbsp;: ",
          v::span(["class" => "dd-value", var_export($value, true)]),
        ]);
      }
    }
    vo::eul();
  }
}

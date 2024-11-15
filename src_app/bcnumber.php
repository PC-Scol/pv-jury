<?php
namespace app;

/**
 * Class bcnumber: un nombre géré par la librairie BCMaths
 */
class bcnumber {
  const PRECISION = 4;

  private static function verifix(string &$value): void {
    if ($value === "") $value = "0";
    $value = str_replace(",", ".", $value);
  }

  static function is_valid($value): bool {
    if ($value === null) return false;
    if ($value instanceof self) return true;
    if (is_string($value)) self::verifix($value);
    return is_numeric($value);
  }

  static function with($value): static {
    if ($value instanceof static) return $value;
    elseif ($value instanceof self) return new static($value->value, false);
    else return new static($value);
  }

  function __construct(string $value="0", bool $verifix=true) {
    if ($verifix) {
      self::verifix($value);
      $value = bcadd($value, "0", static::PRECISION);
    }
    $this->value = $value;
  }

  private string $value;

  function _add(string $value): self {
    self::verifix($value);
    $this->value = bcadd($this->value, $value, static::PRECISION);
    return $this;
  }

  function add(string $value): self {
    self::verifix($value);
    return new self(bcadd($this->value, $value, static::PRECISION), false);
  }

  function _sub(string $value): self {
    self::verifix($value);
    $this->value = bcsub($this->value, $value, static::PRECISION);
    return $this;
  }

  function sub(string $value): self {
    self::verifix($value);
    return new self(bcsub($this->value, $value, static::PRECISION), false);
  }

  function _mul(string $value): self {
    self::verifix($value);
    $this->value = bcmul($this->value, $value, static::PRECISION);
    return $this;
  }

  function mul(string $value): self {
    self::verifix($value);
    return new self(bcmul($this->value, $value, static::PRECISION), false);
  }

  function _div(string $value): self {
    self::verifix($value);
    $this->value = bcdiv($this->value, $value, static::PRECISION);
    return $this;
  }

  function div(string $value): self {
    self::verifix($value);
    return new self(bcdiv($this->value, $value, static::PRECISION), false);
  }

  function _mod(string $divisor): self {
    self::verifix($divisor);
    $this->value = bcmod($this->value, $divisor, static::PRECISION);
    return $this;
  }

  function mod(string $divisor): self {
    self::verifix($divisor);
    return new self(bcmod($this->value, $divisor, static::PRECISION), false);
  }

  function _pow(string $exponent): self {
    self::verifix($exponent);
    $this->value = bcpow($this->value, $exponent, static::PRECISION);
    return $this;
  }

  function pow(string $exponent): self {
    self::verifix($exponent);
    return new self(bcpow($this->value, $exponent, static::PRECISION), false);
  }

  function _powmod(string $exponent, string $divisor): self {
    self::verifix($exponent);
    self::verifix($divisor);
    $this->value = bcpowmod($this->value, $exponent, $divisor, static::PRECISION);
    return $this;
  }

  function powmod(string $exponent, string $divisor): self {
    self::verifix($exponent);
    self::verifix($divisor);
    return new self(bcpowmod($this->value, $exponent, $divisor, static::PRECISION), false);
  }

  function _sqrt(): self {
    $this->value = bcsqrt($this->value, static::PRECISION);
    return $this;
  }

  function sqrt(): self {
    return new self(bcsqrt($this->value, static::PRECISION), false);
  }

  static function min(array $values): ?self {
    $min = null;
    foreach ($values as $value) {
      $value = new static($value);
      if ($min === null) $min = $value;
      elseif ($min->gt($value)) $min = $value;
    }
    return $min;
  }

  static function max(array $values): ?self {
    $max = null;
    foreach ($values as $value) {
      $value = new static($value);
      if ($max === null) $max = $value;
      elseif ($max->lt($value)) $max = $value;
    }
    return $max;
  }

  static function avg(array $values): ?self {
    $count = 0;
    $avg = null;
    foreach ($values as $value) {
      if (!self::is_valid($value)) continue;
      $count++;
      $avg ??= new static();
      $avg->_add($value);
    }
    if ($count > 0) $avg->_div($count);
    return $avg;
  }

  static function min_max_avg(array $values): array {
    $min = null;
    $max = null;
    $count = 0;
    $avg = null;
    foreach ($values as $value) {
      if (!self::is_valid($value)) continue;
      $count++;
      $value = new static($value);
      if ($min === null) $min = $value;
      elseif ($min->gt($value)) $min = $value;
      if ($max === null) $max = $value;
      elseif ($max->lt($value)) $max = $value;
      $avg ??= new static();
      $avg->_add($value);
    }
    if ($count > 0) $avg->_div($count);
    return [$min, $max, $avg];
  }

  static function stdev(array $values): ?self {
    $count = 0;
    $avg = self::avg($values);
    $stdev = null;
    foreach ($values as $value) {
      if (!self::is_valid($value)) continue;
      $count++;
      $term = new static($value);
      $term->_sub($avg);
      $term->_pow(2);
      $stdev ??= new static();
      $stdev->_add($term);
    }
    if ($count > 0) {
      $stdev->_div($count);
      $stdev->_sqrt();
    }
    return $stdev;
  }

  function compare(string $value): int {
    self::verifix($value);
    return bccomp($this->value, $value, static::PRECISION);
  }

  function gt(string $value): bool {
    return $this->compare($value) > 0;
  }

  function ge(string $value): bool {
    return $this->compare($value) >= 0;
  }

  function lt(string $value): bool {
    return $this->compare($value) < 0;
  }

  function le(string $value): bool {
    return $this->compare($value) <= 0;
  }

  function __toString(): string {
    return $this->value;
  }

  function strval(?int $precision=null, bool $trimZeros=false): string {
    $value = $this->value;
    if ($precision !== null) {
      $value = bcadd($this->value, "0", $precision);
    }
    if ($trimZeros) {
      $value = rtrim(rtrim($value, "0"), ".");
    }
    return $value;
  }

  function intval(): int {
    return intval($this->value);
  }

  function floatval(?int $precision=null): float {
    if ($precision === null) return floatval($this->value);
    return floatval(bcadd($this->value, "0", $precision));
  }

  /**
   * retourner un entier ou un flottant en fonction de la présence ou non d'une
   * partie frationnaire
   */
  function numval(?int $precision=null): float|int {
    $value = $this->strval($precision, true);
    if (str_contains($value, ".")) return floatval($value);
    else return intval($value);
  }
}

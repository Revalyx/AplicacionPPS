<?php
use PHPUnit\Framework\TestCase;

final class PruebaTest extends TestCase {
  public function testBasico(){
    $this->assertSame(4, 2+2);
  }
}
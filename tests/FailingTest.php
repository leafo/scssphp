<?php
/**
 * SCSSPHP
 *
 * @copyright 2012-2015 Leaf Corcoran
 *
 * @license http://opensource.org/licenses/gpl-license GPL-3.0
 * @license http://opensource.org/licenses/MIT MIT
 *
 * @link http://leafo.net/scssphp
 */

namespace Leafo\ScssPhp\Tests;

use Leafo\ScssPhp\Compiler;

/**
 * Failing tests
 *
 * @author Anthon Pang <anthon.pang@gmail.com>
 */
class FailingTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->scss = new Compiler();
    }

    /**
     * @param string $id
     * @param string $scss
     * @param string $good
     * @param string $bad
     *
     * @dataProvider provideFailing
     */
    public function testFailing($id, $scss, $good, $bad)
    {
        try {
            $output = $this->compile($scss);

            $this->assertTrue($output != $good, $id);
            $this->assertTrue($output == $bad, $id);
        } catch (\Exception $e) {
            $message = $e->getMessage();

            $this->assertTrue(strpos($message, $good) === false, $id);
            $this->assertTrue(strpos($message, $bad) !== false, $id);
        }
    }

    /**
     * @return array
     */
    public function provideFailing()
    {
        return array(
            array(
                '#28 - @extend working unexpected', <<<'END_OF_SCSS'
#content {

        .social-login {
            display: block;
            float: right;
            margin-right: 15px;
            width: 250px;

                .facebook {
                    display: block;
                    width: 255px;
                    height: 42px;
                    background: transparent url('images/login-btns.png') no-repeat;
                    background-position: 0 0;

                    &:hover {
                        background-position: 0 -43px;
                        }

                    &:focus, &:active {
                        background-position: 0 -86px;
                        }
                }

                .twitter {

                    @extend .facebook;
                    background-position: 0 -129px;

                    &:hover {
                        background-position: 0 -172px;
                    }

                    &:active, &:focus {
                        background-position: 0 -215px;
                    }
                }

        }
}
END_OF_SCSS
                , <<<END_OF_GOOD
#content .social-login {
  display: block;
  float: right;
  margin-right: 15px;
  width: 250px; }
  #content .social-login .facebook, #content .social-login .twitter {
    display: block;
    width: 255px;
    height: 42px;
    background: transparent url("images/login-btns.png") no-repeat;
    background-position: 0 0; }
    #content .social-login .facebook:hover, #content .social-login .twitter:hover {
      background-position: 0 -43px; }
    #content .social-login .facebook:focus, #content .social-login .twitter:focus, #content .social-login .facebook:active, #content .social-login .twitter:active {
      background-position: 0 -86px; }
  #content .social-login .twitter {
    background-position: 0 -129px; }
    #content .social-login .twitter:hover {
      background-position: 0 -172px; }
    #content .social-login .twitter:active, #content .social-login .twitter:focus {
      background-position: 0 -215px; }
END_OF_GOOD
                , <<<END_OF_BAD
#content .social-login {
  display: block;
  float: right;
  margin-right: 15px;
  width: 250px; }
  #content .social-login .facebook, #content .social-login .social-login .twitter, #content .social-login .social-login .twitter {
    display: block;
    width: 255px;
    height: 42px;
    background: transparent url('images/login-btns.png') no-repeat;
    background-position: 0 0; }
    #content .social-login .facebook:hover, #content .social-login .social-login .twitter:hover, #content .social-login .social-login .twitter:hover {
      background-position: 0 -43px; }
    #content .social-login .facebook:focus, #content .social-login .social-login .twitter:focus, #content .social-login .social-login .twitter:focus, #content .social-login .facebook:active, #content .social-login .social-login .twitter:active, #content .social-login .social-login .twitter:active {
      background-position: 0 -86px; }
  #content .social-login .twitter {
    background-position: 0 -129px; }
    #content .social-login .twitter:hover {
      background-position: 0 -172px; }
    #content .social-login .twitter:active, #content .social-login .twitter:focus {
      background-position: 0 -215px; }
END_OF_BAD
            ),
            array(
                '#67 - weird @extend behavior', <<<'END_OF_SCSS'
.nav-bar {
    background: #eee;
    > .item {
        margin: 0 10px;
    }
}


header ul {
    @extend .nav-bar;
    > li {
        @extend .item;
    }
}
END_OF_SCSS
                , <<<END_OF_GOOD
.nav-bar, header ul {
  background: #eee; }
  .nav-bar > .item, header ul > .item, header ul > li {
    margin: 0 10px; }
END_OF_GOOD
                , <<<END_OF_BAD
.nav-bar, header ul {
  background: #eee; }
  .nav-bar > .item, header ul > .item, header ul > header ul > li, header ul > header ul > li, .nav-bar > header ul > li, header ul > .nav-bar > li {
    margin: 0 10px; }
END_OF_BAD
            ),
            array(
                '#107 - incompatible units (example 1)', <<<'END_OF_SCSS'
$gridColumns:             12 !default;
$gridColumnWidth:         60px !default;
$gridGutterWidth:         20px !default;
$gridRowWidth:            ($gridColumns * $gridColumnWidth) + ($gridGutterWidth * ($gridColumns - 1)) !default;

$fluidGridGutterWidth:    percentage($gridGutterWidth/$gridRowWidth) !default;

div {
*margin-left: $fluidGridGutterWidth - (.5 / $gridRowWidth * 100px * 1%);
}
END_OF_SCSS
                , <<<END_OF_GOOD
div {
  *margin-left: 2.07447%; }
END_OF_GOOD
                , <<<END_OF_BAD
div {
  *margin-left: 2.12765%; }
END_OF_BAD
            ),
            array(
                '#107 - incompatible units (example 2)', <<<'END_OF_SCSS'
$gridRowWidth: 20px;

.foo
{
width: (2.5 / $gridRowWidth * 100px * 1% );
}
END_OF_SCSS
                , <<<END_OF_GOOD
.foo {
  width: 12.5%; }
END_OF_GOOD
                , <<<END_OF_BAD
.foo {
  width: 0.13021px; }
END_OF_BAD
            ),
            array(
                '#111 - interpolated string is not the same as regular string', <<<'END_OF_SCSS'
body{
    $test : "1", "2", "3", "4", "5";
    color : index($test, "#{3}");
}
END_OF_SCSS
                , <<<END_OF_GOOD
body {
  color: 3; }
END_OF_GOOD
                , <<<END_OF_BAD
body {
  color : false; }
END_OF_BAD
            ),
            array(
                '#112 - variable arguments type should be arglist', <<<'END_OF_SCSS'
@function test($args...){
    @return type-of($args); // should return arglist, but returns list
}

p{
    color : test("a", "s", "d", "f");
}
END_OF_SCSS
                , <<<END_OF_GOOD
p {
  color: arglist; }
END_OF_GOOD
                , <<<END_OF_BAD
p {
  color : list; }
END_OF_BAD
            ),
            array(
                '#117 - extends and scope', <<<'END_OF_SCSS'
body{
  .to-extend{
    color: red;
  }
  .test{
    @extend .to-extend;
  }
}
END_OF_SCSS
                , <<<END_OF_GOOD
body .to-extend, body .test {
  color: red; }
END_OF_GOOD
                , <<<END_OF_BAD
body .to-extend, body body .test, body body .test {
  color: red; }
END_OF_BAD
            ),
            array(
                '#127 - nesting not working with interpolated strings', <<<'END_OF_SCSS'
.element {
  #{".one, .two"} {
    property: value;
  }
}
END_OF_SCSS
                , <<<END_OF_GOOD
.element .one, .element .two {
  property: value; }
END_OF_GOOD
                , <<<END_OF_BAD
.element .one, .two {
  property: value; }
END_OF_BAD
            ),
            array(
                '#149 - parent selector (&) inside string does not work', <<<'END_OF_SCSS'
.parent {
    $sub: unquote(".child");
    $self: unquote("&.self2");
    &.self { // works perfectly
        content: "should match .parent.self";
    }
    #{$sub} { // works as it should
        content: "should match .parent .child";
    }
    #{$self} { // does not work (see below)
        content: "should match .parent.self2";
    }
}
END_OF_SCSS
                , <<<END_OF_GOOD
.parent.self {
  content: "should match .parent.self"; }
.parent .child {
  content: "should match .parent .child"; }
.parent.self2 {
  content: "should match .parent.self2"; }
END_OF_GOOD
                , <<<END_OF_BAD
.parent.self {
  content: "should match .parent.self"; }
  .parent .child {
    content: "should match .parent .child"; }
  .parent &.self2 {
    content: "should match .parent.self2"; }
END_OF_BAD
            ),
/*************************************************************
            array(
                '#158 - nested extend error', <<<'END_OF_SCSS'
.navbar {
  .navbar-brand {
    font-weight: bold;
    text-shadow: none;
    color: #fff;

    &:hover {
      @extend .navbar-brand;
    }
  }
}
END_OF_SCSS
                , <<<END_OF_GOOD
.navbar .navbar-brand, .navbar .navbar-brand:hover {
  font-weight: bold;
  text-shadow: none;
  color: #fff; }
END_OF_GOOD
                , <<<END_OF_BAD
Fatal error: Allowed memory size of %d bytes exhausted (tried to allocate %d bytes) in %s on line %d
END_OF_BAD
            ),
*************************************************************/
            array(
                '#160 - nesting issue with list', <<<'END_OF_SCSS'
@function H($el:false)
{
  $h: ();
    $newList: ();
    @each $ord in 1,2,3,4,5,6 {
        @if $el {
            $h: h#{$ord $el};
        } @else {
            $h: h#{$ord};
        }
        $newList: append($newList, $h, comma);
    }
    @return $newList;
}

@mixin H($prop, $val, $el:false) {
    $list: H($el);
    #{$list} {
        #{$prop}: $val;
    }
}

#secondary {
    @include H(color,  #e6e6e6);
}
END_OF_SCSS
                , <<<END_OF_GOOD
#secondary h1, #secondary h2, #secondary h3, #secondary h4, #secondary h5, #secondary h6 {
  color: #e6e6e6; }
END_OF_GOOD
                , <<<END_OF_BAD
#secondary h1, h2, h3, h4, h5, h6 {
  color: #e6e6e6; }
END_OF_BAD
            ),
            array(
                '#199 - issue with selectors', <<<'END_OF_SCSS'
.abc {
  color: #ddd;
}

a.abc:hover {
  text-decoration: underline; 
}

small {
  @extend .abc;
  font-weight: italic;
}
END_OF_SCSS
                , <<<END_OF_GOOD
.abc, small {
  color: #ddd; }

a.abc:hover {
  text-decoration: underline; }

small {
  font-weight: italic; }
END_OF_GOOD
                , <<<END_OF_BAD
.abc, small {
  color: #ddd; }

a.abc:hover, a:hover {
  text-decoration: underline; }

small {
  font-weight: italic; }
END_OF_BAD
            ),
            array(
                '#240 - variable interpolation not working correctly', <<<'END_OF_SCSS'
$span: 'span';
$p: 'p';
$div: 'div'; 

$all: $span, $p, $div;

#{$all} {
    a {
        color: red;
    }
}
END_OF_SCSS
                , <<<END_OF_GOOD
span a, p a, div a {
  color: red; }

END_OF_GOOD
                , <<<END_OF_BAD
'span', 'p', 'div' a {
  color: red; }
END_OF_BAD
            ),
            array(
                '#244 - incorrect handling of lists as selectors', <<<'END_OF_SCSS'
@function tester() {
    @return (foo, bar);
}
.test   {
    #{tester()} {
        border: 1px dashed red;
    }
}
END_OF_SCSS
                , <<<END_OF_GOOD
.test foo, .test bar {
  border: 1px dashed red; }
END_OF_GOOD
                , <<<END_OF_BAD
.test foo, bar {
  border: 1px dashed red; }
END_OF_BAD
            ),
/*************************************************************
            array(
                '', <<<'END_OF_SCSS'
END_OF_SCSS
                , <<<END_OF_GOOD
END_OF_GOOD
                , <<<END_OF_BAD
END_OF_BAD
            ),
*************************************************************/
        );
    }

    private function compile($str)
    {
        return trim($this->scss->compile($str));
    }
}

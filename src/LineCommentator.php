<?php

/**
 * SCSSPHP Line Number
 *
 * @author: Gerhard Boden
 * Date: 15.01.2015
 * Time: 00:08
 *
 * Insert line numbers as commentary within the SCSS for better frontend debugging.
 * Works great in combination with https://addons.mozilla.org/de/firefox/addon/firecompass-for-firebug/
 *
 */

namespace Leafo\ScssPhp;


class LineCommentator
{

    const comment_indicator_start = '/*';
    const comment_indicator_end = '*/';

    const block_indicator_start = '{';
    const block_indicator_end = '}';

    const html_comment_indicator_start = '<!--';
    const html_comment_indicator_end = '-->';

    const function_indicator = '@function';
    const return_indicator = '@return';

    const mixin_indicator = '@mixin';

    const include_indicator = '@include';

    const if_statement_indicator = '@if';
    const else_statement_indicator = '@else';

    const loop_indicator_for = "@for";
    const loop_indicator_while = "@while";
    const loop_indicator_each = "@each";

    //we use this to indicate that we are currently looping within a multiline comment
    static protected $inside_multiline;


    /* insert line number as commentary within the SCSS file
     *
     * @return string;
     */


    static function insertLineComments($scss, $filepath = '')
    {

        $lines = $scss;
        $new_scss_content = array();

        $filepath = str_replace('\\', '/', $filepath);

        foreach ($lines as $linenumber => $line) {

            $line = trim($line);
            $nextline = trim(next($lines));

            //check if empty
            if (empty($line)) {
                continue;
            }

            //check if line is a commment
            if (self::isComment($line) || self::$inside_multiline) {
                $new_scss_content[] = $line;
                continue;
            }

            //write line numbers for selectors only to reduce overhead
            if (
                self::isSelector($line, $nextline) == FALSE ||
                self::isFunction($line) == TRUE ||
                self::isMixin($line) == TRUE ||
                self::isInclude($line) == TRUE ||
                self::isCondition($line) == TRUE ||
                self::isLoop($line) == TRUE
            ) {
                $new_scss_content[] = $line;
                continue;
            }


            //output line commment
            $new_scss_content[] = self::comment_indicator_start . ' line ' . ($linenumber + 1) . ', ' . $filepath . ' ' . self::comment_indicator_end;

            $new_scss_content[] = $line;

        }


        return implode("\n", $new_scss_content);
    }

    /*
     * looking for selector block:
     * the opening  bracket could be in the same line or in the next one (since we've cleaned empty lines)
     * also we don't want to confuse a selector block with a property block or write the comment above the bracket
     * itself (in case it's in a new line)
     *
     * @return boolean
     */
    static function isSelector($line, $nextline = NULL)
    {

        if (
            (strpos($line, self::block_indicator_start) !== FALSE || strpos($nextline, self::block_indicator_start) === 0)
            && strpos($line, self::block_indicator_start) !== 0
        ) {
            return true;
        }

        return false;

    }

    /*
     * ignore mixins. mixins will spread inside selectors after compilation
     *
     * @return: boolean
     */

    static function isMixin($line)
    {
        if (strpos($line, self::mixin_indicator) !== FALSE) {
            return true;
        }

        return false;
    }


    /*
     * ignore functions
     *
     * @return: boolean
     */

    static function isFunction($line)
    {

        if
        (
            strpos($line, self::function_indicator) !== FALSE ||
            strpos($line, self::return_indicator) !== FALSE
        ) {
            return true;
        }

        return false;

    }


    /*
     * ignore include
     *
     * @return: boolean
     */

    static function isInclude($line)
    {

        if (strpos($line, self::include_indicator) !== FALSE) {
            return true;
        }

        return false;

    }

    /*
     * dont't put a line number above if statement, since it will result in an empty line within the
     * compiled scss
     */
    static function isCondition($line) {
        if
        (
            strpos($line, self::if_statement_indicator) !== FALSE ||
            strpos($line, self::else_statement_indicator) !== FALSE
        ) {
            return true;
        }

        return false;

    }

    /*
    * dont't put a line number above loops, since it will result in an empty line within the
    * compiled scss
     */
    static function isLoop($line) {

        if
        (
            strpos($line, self::loop_indicator_for) !== FALSE ||
            strpos($line, self::loop_indicator_each) !== FALSE ||
            strpos($line, self::loop_indicator_while) !== FALSE
        ) {
            return true;
        }

        return false;

    }


    /*
     * we don't want to mess around with existing comments since this can easily lead to parsing errors.
     *
     * @return boolean
     */

    static function isComment($line)
    {

        /* a comment has started, but did not end in the same line */
        if (strpos($line, self::comment_indicator_start) !== FALSE && strpos($line, self::comment_indicator_end) === FALSE) {
            self::$inside_multiline = TRUE;
            return true;

            /* check for comment to end */
        } else if (strpos($line, self::comment_indicator_end) !== FALSE) {
            self::$inside_multiline = FALSE;
            return true;
        }

        /*same check for html comments within scss.. just in case someone is having a bad day
          scssphp will remove this tags later on, but still has a problem with it if we wrap an CSS commentary around it*/

        if (strpos($line, self::html_comment_indicator_start) !== FALSE && strpos($line, self::html_comment_indicator_end) === FALSE) {
            self::$inside_multiline = TRUE;
            return true;

        } else if (strpos($line, self::comment_indicator_end) !== FALSE) {
            self::$inside_multiline = FALSE;
            return true;
        }

        return false;
    }

}
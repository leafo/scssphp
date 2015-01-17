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
    const property_indicator = ': ';

    const html_comment_indicator_start = '<!--';
    const html_comment_indicator_end = '-->';

    //we use this to indicate that we are currently looping within a multiline comment
    static protected $inside_multiline;


    /* insert line number as commentary within the SCSS file
     *
     * @return string;
     */


    static function insertLineComments($scss, $filepath)
    {
        //delete emtpy lines from array
        $scss = array_filter($scss,
            function ($value) {
                $value = trim($value);
                return !empty($value);
            });

        $lines = $scss;
        $new_scss_content = array();

        $filepath = str_replace('\\','/',$filepath);

        foreach ($lines as $linenumber => $line) {

            $line = trim($line);
            $nextline = trim(next($lines));

            //check if line is a commment
            if (self::isComment($line) || self::$inside_multiline) {
                $new_scss_content[] = $line;
                continue;
            }

            //only write line numbers for selectors to reduce overhead
            if (self::isSelector($line, $nextline) == FALSE) {
                $new_scss_content[] = $line;
                continue;
            }


            //output line commment
            $new_scss_content[] = self::comment_indicator_start . ' line ' . ($linenumber + 1) . ', ' . $filepath . ' ' . self::comment_indicator_end;

            $new_scss_content[] = $line;

        }


        return implode("\r\n", $new_scss_content);
    }

    /*
     * looking for selector block
     * @return boolean
     */
    static function isSelector($line, $nextline = NULL)
    {

        $bracket_position = strpos($nextline, self::block_indicator_start);

        if ((strpos($line, self::block_indicator_start) !== FALSE || strpos($nextline, self::block_indicator_start) === 0)
            && self::isProperty($line) === FALSE && strpos($line, self::block_indicator_start) !== 0
        ) {

            return true;
        }

        return false;

    }

    /*
     * check for property
     *
     * @return boolean
     */
    static function isProperty($line)
    {
        if (strpos($line, self::property_indicator) !== FALSE) {
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
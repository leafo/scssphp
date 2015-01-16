<?php
/**
 * User: Gerhard Boden
 * Date: 015. 15.01.2015
 * Time: 00:08
 *
 * insert line number as commentary within the SCSS for better frontend debugging
 * works great in combination with https://addons.mozilla.org/de/firefox/addon/firecompass-for-firebug/
 *
 */

namespace Leafo\ScssPhp;

class LineCommentator
{

    static protected $comment_indicator_start = '/*';
    static protected $comment_indicator_end = '*/';

    static protected $html_comment_indicator_start = '<!--';
    static protected $html_comment_indicator_end = '-->';

    //we use this to indicate that we are currently looping within a multiline comment
    static protected $inside_multiline;

    /* insert line number as commentary within the SCSS
     *
     * @return string;
     */

    static function insertLineComments($scss, $filepath)
    {

        $lines = $scss;
        $linenumber = 0;
        $new_scss_content = array();

        foreach ($lines as $line) {
            $linenumber++;
            $line = trim($line);

            //check if empty

            /* note: there will most likely still be line comments for empty lines in the compiled css.
               the reason for the other empty lines is that scssphp will shift our line commentary while compiling.
               but this is nothing to worry about, since our debugging information  (the line numbers) will still be correct */

            if (empty($line)) {
                continue;
            }

            //check for commment
            if (self::isComment($line) || self::$inside_multiline) {
                $new_scss_content[] = $line;
                continue;
            }

            $new_scss_content[] = self::$comment_indicator_start . ' line ' . $linenumber . ', ' . $filepath . ' ' . self::$comment_indicator_end;
            $new_scss_content[] = $line;

        }

        return implode("\n", $new_scss_content);
    }


    /*
     * we don't want to mess arozbd with existing comments since this can easily lead to parsing errors.
     *
     * @return boolean
     */

    static function isComment($line)
    {

        /* a comment has started, but did not end in the same line */
        if (strpos($line, self::$comment_indicator_start) !== FALSE && strpos($line, self::$comment_indicator_end) === FALSE) {
            self::$inside_multiline = TRUE;
            return true;

        /* check for comment to end */
        } else if (strpos($line, self::$comment_indicator_end) !== FALSE) {
            self::$inside_multiline = FALSE;
            return true;
        }

        /*same check for html comments within scss.. just in case someone is having a bad day
          scssphp will remove this tags later on, but still has a problem with it if we wrap an CSS commentary around it*/

        if (strpos($line, self::$html_comment_indicator_start) !== FALSE && strpos($line, self::$html_comment_indicator_end) === FALSE) {
            self::$inside_multiline = TRUE;
            return true;

        } else if (strpos($line, self::$comment_indicator_end) !== FALSE) {
            self::$inside_multiline = FALSE;
            return true;
        }

        return false;
    }


}
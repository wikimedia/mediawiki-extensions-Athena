<?php

class AthenaHelper
{

    /**
     * Load the probabilities related to the given variable
     *
     * @param $var string
     * @param $varFalg 0|1
     * @param $given string
     * @param $givenFlag 0|1
     * @return double|bool
     */
    static function loadProbabilities($var, $varFlag, $given, $givenFlag)
    {
        $db = wfGetDB(DB_MASTER);

        $whereStatement = " ap_variable='{$var}'";

        if ($varFlag) {
            $whereStatement .= " AND ap_variable_not=1";
        }

        if ($given) {
            $whereStatement .= " AND ap_given='{$given}'";
        }

        if ($givenFlag) {
            $whereStatement .= " AND ap_given_not=1";
        }

        $sql = "SELECT ap_value FROM {$db->tableName( 'athena_probability' )} WHERE {$whereStatement};";

        echo($sql);

        $res = $db->query($sql, __METHOD__);
        $row = $db->fetchObject($res);

        if ($row) {
            return $row->ap_value;
        }

        // else we are bork and so let's say false
        echo('Something went wrong :(');
        return false;
    }

    /**
     * Load the weighting for a given variable
     *
     * @param $prob double
     * @param $userAge int
     * @param $links double
     * @param $syntax double
     * @param $language boolean
     * @param $broken boolean
     * @param $deleted boolean
     * @param $wanted boolean
     * @param $namespace int
     * @param $title string
     * @param $content string
     * @param $comment string
     * @param $user int
     */
    static function logAttempt($prob, $userAge, $links, $syntax, $language, $broken, $deleted, $wanted,
                                $namespace, $title, $content, $comment, $user) {
        $db = wfGetDB(DB_MASTER);

        $language = !$language;

        $userAge = AthenaHelper::makeSQLNull($userAge);
        $links = AthenaHelper::makeSQLNull($links);
        $syntax = AthenaHelper::makeSQLNull($syntax);
        $language = AthenaHelper::makeSQLNull($language);
        $broken = AthenaHelper::makeSQLNull($broken);
        $deleted = AthenaHelper::makeSQLNull($deleted);
        $wanted = AthenaHelper::makeSQLNull($wanted);
        $comment = AthenaHelper::makeSQLNull($comment);

        $insertStatement = " (NULL, {$prob}, {$userAge}, {$links}, {$syntax}, {$language}, {$broken}, ${deleted}, ${wanted})";

        $sql = "INSERT INTO {$db->tableName( 'athena_log' )} VALUES {$insertStatement};";

        echo($sql);

        $db->query($sql, __METHOD__);

        // Get last inserted ID
        $sql = 'select LAST_INSERT_ID() as id;';
        $res = $db->query($sql);
        $row = $db->fetchObject( $res );
        $id = $row->id;

        $title = mysql_real_escape_string ($title);
        $content = mysql_real_escape_string ($content);
        // TODO security
        $insertStatement = " ({$id}, {$namespace}, '{$title}', '{$content}', {$comment}, {$user}, CURRENT_TIMESTAMP, NULL, NULL)";

        $sql = "INSERT INTO {$db->tableName( 'athena_page_details' )} VALUES {$insertStatement};";

        echo($sql);

        $db->query($sql, __METHOD__);

    }


    /**
     * Log the page creation attempt
     *
     * @param $var string
     * @return double|bool
     */
    static function loadWeightings($var)
    {
        $db = wfGetDB(DB_MASTER);

        $whereStatement = " aw_variable='{$var}'";

        $sql = "SELECT aw_value FROM {$db->tableName( 'athena_weighting' )} WHERE {$whereStatement};";

        echo($sql);

        $res = $db->query($sql, __METHOD__);
        $row = $db->fetchObject($res);

        if ($row) {
            return $row->aw_value;
        }

        // else we are bork and so let's say false
        echo('Something went wrong :(');
        return false;
    }

    /**
     * Loads the language classifier
     * @return Text_LanguageDetect
     */
    static function getClassifier() {
        global $IP;

        // Code for Text-LanguageDetect
        require_once $IP . '\extensions\Athena\libs\Text_LanguageDetect-0.3.0\Text\LanguageDetect.php';
        $classifier = new Text_LanguageDetect;
        // Set it to return ISO 639-1 (same format as MediaWiki)
        $classifier->setNameMode(2);
        return $classifier;
    }

    /**
     * Converts empty variables to be a NULL string for SQL purposes
     *
     * @param $var type
     * @return string|type
     */
    static function makeSQLNull( $var ) {
        if( empty($var) ) {
            return "NULL";
        }
        return $var;
    }
}
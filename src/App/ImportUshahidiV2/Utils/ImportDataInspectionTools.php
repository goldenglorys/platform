<?php

namespace Ushahidi\App\ImportUshahidiV2\Utils;

use Ushahidi\App\ImportUshahidiV2\Contracts\ImportDataInspectionTools as ImportDataInspectionToolsContract;
use Ushahidi\App\ImportUshahidiV2\Jobs\Concerns\ConnectsToV2DB;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ImportDataInspectionTools implements ImportDataInspectionToolsContract
{

    use ConnectsToV2DB;

    public function suggestNumberStorage(int $fieldId): string
    {
        return $this->doNumberStudy($this->getFieldSamples($fieldId));
    }

    protected function getFieldSamples(int $fieldId)
    {
        return $this->getConnection()
            ->table('form_response')
            ->select('form_response')
            ->where('form_field_id', '=', $fieldId)
            ->where('form_response', '<>', '');
    }

    protected function doNumberStudy($samples) : string
    {
        // initial suggestion is integer, since that's what can
        // usually hold the most significant figures in v3+
        $suggestion = "int";

        // review samples
        foreach ($samples->cursor() as $n) {
            if ($suggestion == 'int') {
                // test the sample for integer parseability
                if (is_numeric($n)) {
                    if (!preg_match("/^-?[0-9]+$/", $n)) {
                        // a number but not an int
                        $suggestion = "decimal";
                    }
                } else {
                    return "varchar";    // stop evaluation
                }
            } else { // if ($suggestion = 'decimal') {
                if (!is_numeric($n)) {
                    return "varchar";    // stop evaluation
                }
            }
        }
    }

    protected $knownDateFormats = [
        'm#d#y', 'm#d#Y',
        'd#m#y', 'd#m#Y',
        'y#m#d', 'Y#m#d'
    ];

    // protected $knownTimeFormats = [
    //     'G#i#s', 'G#i#s#u', 'G#i#s a', 'G#i#s#u a'
    // ];

    public function tryDateDecodeFormats(int $fieldId) : Array
    {
        /* Obtain values for the given field */
        $samples = $this->getFieldSamples($fieldId);
        $results = $this->doDateFormatStudy($samples, $this->knownDateFormats);

        return $results;
    }

    // public function tryDateTimeDecodeFormats(int $fieldId) : Array
    // {
    //     /* Obtain values for the given field */
    //     $samples = $this->getFieldSamples($fieldId);
    //     $results = $this->doDateFormatStudy($samples, $this->combineDateAndTimeFormats());

    //     return $results;
    // }

    // protected function combineDateAndTimeFormats()
    // {
    //     $combinedFormats = [];
    //     foreach ($this->knownDateFormats as $df) {
    //         foreach ($this->knownTimeFormats as $tf) {
    //         $combinedFormats[] = $df . " " . $tf;
    //         }
    //     }
    //     return $combinedFormats;
    // }

    protected function doDateFormatStudy($samples, $formats)
    {
        $results = [];
        $sample_size = $samples->count();
        foreach ($samples->cursor() as $v) {
            $this->updateDateFormatStudy($v->form_response, $formats, $results);
        }

        // Normalize and sort results
        $results = collect($results)->filter(function ($value) {
            return $value > 0;
        })->map(function ($item, $key) use ($sample_size) {
            # normalize results to sample size
            return [ 'format' => $key , 'score' => ($item / $sample_size) ];
        })->values()->sortByDesc('score')->values();

        return $results->all();
    }

    protected function updateDateFormatStudy($v, $formats, &$results)
    {
        $minWarnings = PHP_INT_MAX;
        $maxLength = 0;
        $tests = [];

        // test each of the given formats
        foreach ($formats as $f) {
            $t = date_parse_from_format($f, $v);
            $vl = $this->valuesLength($t);
            // test result: [ noerror(1)/error(0), warningCount, valuesLength ]
            $tests[$f] = [ ($t['error_count'] == 0) ?: 0, $t['warning_count'], $vl ];
            $minWarnings = min($minWarnings, $t['warning_count']);
            $maxLength = max($maxLength, $vl);
        }

        // score each format test result
        $scores = array_map(function ($t) use ($maxLength, $minWarnings) {
            return $t[0] ?  // score non errored tests, with most length of results and least warnigs
                ( ($t[2] / floatval($maxLength)) / ($t[1]-$minWarnings+1) )
                : 0.0;
        }, $tests);

        // aggregate score to current study results
        foreach ($scores as $format => $s) {
            $results[$format] = ( $results[$format] ?? 0 ) + $s;
        }
    }

    private function valuesLength($date_arr)
    {
        return strlen(
            strval($date_arr['year']) .
            strval($date_arr['month']) .
            strval($date_arr['day']) .
            strval($date_arr['hour']) .
            strval($date_arr['minute']) .
            strval($date_arr['second']) .
            strval($date_arr['fraction'])
        );
    }
}
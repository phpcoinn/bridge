<?php

use Web3\Contracts\Ethabi;
use Web3\Utils;

class MyEthAbi extends Ethabi
{
    public function decodeParameters($types, $param)
    {
        if (!is_string($param)) {
            throw new InvalidArgumentException('The param must be string.');
        }

        // change json to array
        $outputTypes = [];
        if ($types instanceof stdClass && isset($types->outputs)) {
            $types = Utils::jsonToArray($types, 2);
        }
        if (is_array($types) && isset($types['outputs'])) {
            $outputTypes = $types;
            $types = [];

            foreach ($outputTypes['outputs'] as $output) {
                if (isset($output['type'])) {
                    $types[] = $output['type'];
                }
            }
        } else {
            $inputTypes = $types;
            $types = [];
            foreach ($inputTypes as $input) {
                $types[] = $input->type;
            }
        }
        $typesLength = count($types);
        $abiTypes = $this->parseAbiTypes($types);

        // decode with tuple type
        $results = [];
        $decodeResults = $this->types['tuple']->decode(Utils::stripZero($param), 0, [ 'coders' => $abiTypes ]);
        for ($i = 0; $i < $typesLength; $i++) {
            if (isset($outputTypes['outputs'][$i]['name']) && empty($outputTypes['outputs'][$i]['name']) === false) {
                $results[$outputTypes['outputs'][$i]['name']] = $decodeResults[$i];
            } else {
                $results[$i] = $decodeResults[$i];
            }
        }
        return $results;
    }

    function decodeData($types, $data) {
        $abiTypes = $this->parseAbiTypes($types);
        $decodeResults = $this->types['tuple']->decode(Utils::stripZero($data), 0, [ 'coders' => $abiTypes ]);
        return $decodeResults;
    }
}
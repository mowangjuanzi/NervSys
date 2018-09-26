<?php

/**
 * Multi-Process Controller Extension
 *
 * Copyright 2016-2018 秋水之冰 <27206617@qq.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace ext;

use core\parser\data;

use core\handler\factory;
use core\handler\platform;

class mpc extends factory
{
    //Job list
    private $jobs = [];

    //Process quantity
    private $runs = 10;

    //Process wait option
    private $wait = true;

    //PHP key name in "system.ini"
    private $php_key = 'php';

    //PHP executable path in "system.ini"
    private $php_exe = '';

    //Basic command
    private $php_cmd = '';

    /**
     * Add job
     *
     * @param array $job
     * cmd:  string, command
     * data: array,  data pack to pass
     * pipe: array,  pipe data to pass
     * argv: array,  argv data to pass
     *
     * @return $this
     * @throws \Exception
     */
    public function add(array $job = []): object
    {
        //Check cmd
        if (!isset($job['cmd'])) {
            throw new \Exception('Missing "cmd" parameter!', E_USER_ERROR);
        }

        $this->jobs[] = &$job;

        unset($job);
        return $this;
    }

    /**
     * Commit jobs
     *
     * @return array
     * @throws \Exception
     */
    public function commit(): array
    {
        //Check jobs
        if (empty($this->jobs)) {
            return [];
        }

        //Check php cli settings
        if ('' === $this->php_exe) {
            if (!isset(parent::$cli[$this->php_key])) {
                throw new \Exception('[' . $this->php_key . '] NOT configured in "system.ini"', E_USER_ERROR);
            }

            $this->php_exe = parent::$cli[$this->php_key];
        }

        //Split jobs
        $job_packs = count($this->jobs) > $this->runs ? array_chunk($this->jobs, $this->runs, true) : [$this->jobs];

        //Build basic command
        $this->php_cmd = $this->php_exe . ' "' . ROOT . 'api.php"';

        if ($this->wait) {
            $this->php_cmd .= ' --ret';
        }

        $result = [];

        foreach ($job_packs as $jobs) {
            //Execute jobs and merge result
            if (!empty($data = $this->execute($jobs))) {
                $result += $data;
            }
        }

        unset($job_packs, $jobs, $data);
        return $result;
    }

    /**
     * Execute jobs
     *
     * @param array $jobs
     *
     * @return array
     * @throws \Exception
     */
    private function execute(array $jobs): array
    {
        //Resource list
        $resource = [];

        //Start process
        foreach ($jobs as $key => $job) {
            //Add cmd
            $cmd = $this->php_cmd . ' --cmd "' . data::encode($job['cmd']) . '"';

            //Append data
            if (!empty($job['data'])) {
                $cmd .= ' --data "' . data::encode(json_encode($job['data'])) . '"';
            }

            //Append pipe
            if (!empty($job['pipe'])) {
                $cmd .= ' --pipe "' . data::encode(json_encode($job['pipe'])) . '"';
            }

            //Append argv
            if (!empty($job['argv'])) {
                $cmd .= ' ' . implode(' ', $job['argv']);
            }

            //Create process
            $process = proc_open(platform::cmd_proc($cmd), [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);

            if (is_resource($process)) {
                $resource[$key]['res']  = true;
                $resource[$key]['cmd']  = $job['cmd'];
                $resource[$key]['pipe'] = $pipes;
                $resource[$key]['proc'] = $process;
            } else {
                $resource[$key]['res'] = false;
            }
        }

        unset($jobs, $key, $job, $cmd, $pipes);

        //Check wait option
        if (!$this->wait) {
            return [];
        }

        //Collect result
        $result = $this->collect($resource);

        unset($resource, $process);
        return $result;
    }


    /**
     * Collect result
     *
     * @param array $resource
     *
     * @return array
     */
    private function collect(array $resource): array
    {
        $result = [];

        while (!empty($resource)) {
            foreach ($resource as $key => $item) {
                //Collect process
                $result[$key]['res'] = $item['res'];
                $result[$key]['cmd'] = $item['cmd'];

                //Remove failed process
                if (!$item['res']) {
                    unset($resource[$key]);
                    continue;
                }

                //Build process result
                if (!isset($result[$key]['data'])) {
                    $result[$key]['data'] = '';
                }

                //Remove finished process
                if (feof($item['pipe'][1])) {
                    foreach ($item['pipe'] as $pipe) {
                        fclose($pipe);
                    }

                    //Close process
                    proc_close($item['proc']);

                    unset($resource[$key], $pipe);
                    continue;
                }

                //Read pipe
                $result[$key]['data'] .= trim((string)fgets($item['pipe'][1], 4096));
            }
        }

        //Parse data content
        foreach ($result as $key => $item) {
            if ('' !== $item['data'] && !is_null($json = json_decode($item['data'], true))) {
                $result[$key]['data'] = $json;
            }
        }

        unset($resource, $key, $item, $json);
        return $result;
    }
}
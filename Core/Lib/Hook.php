<?php

/**
 * NS Hook library
 *
 * Copyright 2016-2021 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2021 秋水之冰 <27206617@qq.com>
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

namespace Core\Lib;

use Core\Execute;
use Core\Factory;
use Core\Reflect;

/**
 * Class Hook
 *
 * @package Core\Lib
 */
class Hook extends Factory
{
    public array $before = [];
    public array $after  = [];

    /**
     * Add hook fn before input_c
     *
     * @param string $input_c
     * @param string $hook_class
     * @param string $hook_method
     *
     * @return $this
     */
    public function addBefore(string $input_c, string $hook_class, string $hook_method): self
    {
        $this->before[$input_c] ??= [];
        array_unshift($this->before[$input_c], [$hook_class, $hook_method]);

        unset($input_c, $hook_class, $hook_method);
        return $this;
    }

    /**
     * Add hook fn after input_c
     *
     * @param string $input_c
     * @param string $hook_class
     * @param string $hook_method
     *
     * @return $this
     */
    public function addAfter(string $input_c, string $hook_class, string $hook_method): self
    {
        $this->after[$input_c][] = [$hook_class, $hook_method];

        unset($input_c, $hook_class, $hook_method);
        return $this;
    }

    /**
     * Check hook fn pass status
     *
     * @param Execute $execute
     * @param Reflect $reflect
     * @param string  $input_c
     * @param array   $hook_list
     *
     * @return bool
     */
    public function checkPass(Execute $execute, Reflect $reflect, string $input_c, array $hook_list): bool
    {
        $fn_list = $this->getFn($input_c, $hook_list);

        foreach ($fn_list as $hook_fn) {
            if (!$this->callFn($execute, $reflect, $hook_fn)) {
                unset($execute, $reflect, $input_c, $hook_list, $fn_list, $hook_fn);
                return false;
            }
        }

        unset($execute, $reflect, $input_c, $hook_list, $fn_list, $hook_fn);
        return true;
    }

    /**
     * Get function list from input_c
     *
     * @param string $input_c
     * @param array  $h_list
     *
     * @return array
     */
    private function getFn(string $input_c, array $h_list): array
    {
        $fn_list = [];
        $c_list  = [];

        $c_part = false !== strpos($input_c, '/') ? explode('/', $input_c) : [$input_c];

        foreach ($c_part as $value) {
            $c_list[] = $value;
            $c_string = implode('/', $c_list);

            if (isset($h_list[$c_string])) {
                $fn_list = array_merge($fn_list, $h_list[$c_string]);
            }
        }

        unset($input_c, $h_list, $c_list, $c_part, $value, $c_string);
        return $fn_list;
    }

    /**
     * Call hook function
     *
     * @param Execute $execute
     * @param Reflect $reflect
     * @param array   $hook_fn
     *
     * @return bool
     */
    private function callFn(Execute $execute, Reflect $reflect, array $hook_fn): bool
    {
        try {
            $result = $execute->runScript($reflect, $hook_fn[0], $hook_fn[1], implode('/', $hook_fn));

            unset($execute, $reflect, $hook_fn);
            return (empty($result) || true === current($result));
        } catch (\Throwable $throwable) {
            App::new()->showDebug($throwable, true);
            unset($execute, $reflect, $hook_fn, $throwable);
            return false;
        }
    }
}
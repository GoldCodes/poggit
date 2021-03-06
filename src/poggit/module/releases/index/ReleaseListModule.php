<?php

/*
 * Poggit
 *
 * Copyright (C) 2016 Poggit
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace poggit\module\releases\index;

use poggit\module\VarPageModule;

class ReleaseListModule extends VarPageModule {
    public function getName(): string {
        return "plugins";
    }

    public function getAllNames(): array {
        return ["plugins", "pi", "index"];
    }

    protected function selectPage() {
        $query = array_filter(explode("/", $this->getQuery(), 2));
        if(count($query) === 0) {
            throw new SearchReleaseListPage($_REQUEST);
        } elseif(count($query) === 1) {
            switch($query[0]) {
                case "cat":
                case "category":
                case "tag":
                case "tags":
                    throw new ListTagsReleaseListPage($_REQUEST);
                default:
                    throw new SearchReleaseListPage($_REQUEST, <<<EOM
<p>Cannot understand your query</p> <!-- TODO implement more logic here -->
EOM
                    );
            }
        } else {
            assert(count($query) === 2);
            list($c, $v) = $query;
            switch($c) {
                case "by":
                case "author":
                case "authors":
                case "in":
                case "repo":
                    throw new PluginsByRepoReleaseListPage($v, $_REQUEST);
                case "called":
                case "name":
                    throw new PluginsByNameReleaseListPage($v);
                default:
                    throw new SearchReleaseListPage($_REQUEST, <<<EOM
<p>Cannot understand your query</p> <!-- TODO implement more logic here -->
EOM
                    );
            }
        }
    }

    protected function titleSuffix(): string {
        return " | Poggit Releases";
    }
}

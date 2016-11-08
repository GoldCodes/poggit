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

namespace poggit\timeline;

class WelcomeTimeLineEvent extends TimeLineEvent {

    public function output() {
        ?>
        <h3>Welcome to Poggit!</h3>
        <p>More welcome stuff here... <!-- TODO --></p>
        <?php
    }

    public function getType() : int {
        return TimeLineEvent::EVENT_WELCOME;
    }
}
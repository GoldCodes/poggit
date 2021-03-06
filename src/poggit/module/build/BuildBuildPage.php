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

namespace poggit\module\build;

use poggit\builder\cause\V2BuildCause;use poggit\builder\lint\BuildResult;use poggit\builder\ProjectBuilder;use poggit\embed\EmbedUtils;use poggit\module\VarPage;use poggit\Poggit;use poggit\utils\internet\CurlUtils;use poggit\utils\internet\GitHubAPIException;use poggit\utils\internet\MysqlUtils;use poggit\utils\SessionUtils;

class BuildBuildPage extends VarPage {
    /** @var string|null */
    public static $projectPath = null;

    /** @var string */
    private $ownerName;
    /** @var string */
    private $repoName;
    /** @var string */
    private $projectName;
    /** @var string */
    private $internalBuildNumber;
    /** @var int */
    private $buildClass;
    /** @var \stdClass */
    private $repo;
    /** @var array */
    private $build;
    /** @var BuildResult */
    private $lint;
    /** @var string */
    private $permLink;

    public function __construct(string $user, string $repo, string $project, string $internalBuildNumber) {
        $this->ownerName = $user;
        $this->repoName = $repo;
        $this->projectName = $project;

        $class = "dev";
        if(strpos($internalBuildNumber, ":") !== false) {
            list($class, $internalBuildNumber) = explode(":", strtolower($internalBuildNumber), 2);
        }
        switch($class) {
            case "dev":
                $this->buildClass = ProjectBuilder::BUILD_CLASS_DEV;
                break;
//            case "beta":
//                $this->buildClass = ProjectBuilder::BUILD_CLASS_BETA;
//                break;
//            case "rc":
//                $this->buildClass = ProjectBuilder::BUILD_CLASS_RELEASE;
//                break;
            case "pr":
                $this->buildClass = ProjectBuilder::BUILD_CLASS_PR;
                break;
        }
        if(!isset($this->buildClass) or !is_numeric($internalBuildNumber)) {
            $rp = json_encode(Poggit::getRootPath(), JSON_UNESCAPED_SLASHES);
            throw new RecentBuildPage(<<<EOD
<p>Invalid request. The #build is not numeric. The correct syntax should be:</p>
<pre><script>document.write(window.location.origin + $rp);</script>ci/$user/$repo/$project/{&lt;buildClass&gt;:}&lt;buildNumber&gt;</pre>
<p>For example:</p>
<pre>
<script>document.write(window.location.origin + $rp);</script>ci/$user/$repo/$project/3
<script>document.write(window.location.origin + $rp);</script>ci/$user/$repo/$project/beta:2
<script>document.write(window.location.origin + $rp);</script>ci/$user/$repo/$project/rc:1
</pre>
EOD
            );
        }
        $this->internalBuildNumber = (int) $internalBuildNumber;

        $session = SessionUtils::getInstance();
        $token = $session->getAccessToken();
        try {
            $this->repo = CurlUtils::ghApiGet("repos/$this->ownerName/$this->repoName", $token);
        } catch(GitHubAPIException $e) {
            $name = htmlspecialchars($session->getLogin()["name"]);
            $repoNameHtml = htmlspecialchars($user . "/" . $repo);
            throw new RecentBuildPage(<<<EOD
<p>The repo $repoNameHtml does not exist or is not accessible to your GitHub account (<a href="$name"?>@$name</a>).</p>
EOD
            );
        }

        $builds = MysqlUtils::query("SELECT r.owner AS repoOwner, r.name AS repoName, r.private AS isPrivate,
            p.name AS projectName, p.path AS projectPath, p.type AS projectType, p.framework AS projectModel,
            b.buildId AS buildId, b.resourceId AS rsrcId, b.cause AS buildCause,
            b.branch AS buildBranch, unix_timestamp(b.created) AS buildCreation
            FROM builds b INNER JOIN projects p ON b.projectId = p.projectId 
            INNER JOIN repos r ON p.repoId = r.repoId
            WHERE r.repoId = ? AND r.build = 1 AND p.name = ? AND b.class = ? AND b.internal = ?",
            "isii", $this->repo->id, $this->projectName, $this->buildClass, $this->internalBuildNumber);
        if(count($builds) === 0) {
            $pn = htmlspecialchars($this->projectName);
            throw new RecentBuildPage(<<<EOD
<p>The repo does not have a project called $pn, or the project does not have such a build.</p>
EOD
            );
        }
        $this->build = $builds[0];
        $this->lint = BuildResult::fetchMysql((int) $this->build["buildId"]);
        $this->permLink = Poggit::getRootPath() . "babs/" . dechex((int) $this->build["buildId"]);
    }

    public function getTitle(): string {
        return htmlspecialchars("Build #$this->internalBuildNumber | $this->projectName ($this->ownerName/$this->repoName)");
    }

    public function output() {
        $rp = Poggit::getRootPath();
        ?>
        <div class="buildpagewrapper">
        <div class="buildpage">
            <div class="buildinfo">
        <h1>
            <?= htmlspecialchars($this->projectName) ?>:
            <?= ProjectBuilder::$BUILD_CLASS_HUMAN[$this->buildClass] ?> build
            #<?= $this->internalBuildNumber ?>
        </h1>
        <div>
            <p>
                <a href="<?= $rp ?>ci/<?= $this->repo->full_name ?>/<?= urlencode($this->projectName) ?>">
                    <?= htmlspecialchars($this->projectName) ?></a> from repo:
                <a href="<?= $rp ?>ci/<?= $this->repo->owner->login ?>">
                    <?php EmbedUtils::displayUser($this->repo->owner) ?></a>
                / <a href="<?= $rp ?>ci/<?= $this->repo->full_name ?>"><?= $this->repo->name ?></a>
                <?php EmbedUtils::ghLink($this->repo->html_url) ?>
                <?php if(trim($this->build["projectPath"], "/") !== "") { ?>
                    (In directory <code class="code"><?= htmlspecialchars($this->build["projectPath"]) ?></code>
                    <?php EmbedUtils::ghLink($this->repo->html_url . "/tree/" . $this->build["buildBranch"] . "/" .
                        $this->build["projectPath"]) ?>)
                <?php } ?>
            </p>
            <p>Build created: <span class="time" data-timestamp="<?= $this->build["buildCreation"] ?>"></span></p>
            <p>Permanent link:
                <a href="<?= $this->permLink ?>">
                    <script>document.write(window.location.origin + <?= json_encode($this->permLink) ?>);</script>
                </a>
            </p>
        </div>
        <h2>Download build</h2>
        <div>
            <p>
                <?php
                $link = Poggit::getRootPath() . "r/" .$this->build["rsrcId"] . "/" . $this->projectName . ".phar";
                ?>
                <a href="<?= $link ?>">
                    <span class="action" onclick='window.location = <?= json_encode($link, JSON_UNESCAPED_SLASHES) ?>;'>
                    Direct Download</span></a> or
                <span class="action" onclick='promptDownloadResource(<?= json_encode($this->build["rsrcId"])
                    ?>, <?= json_encode($this->projectName) ?> + ".phar")'>Download with custom name</span>
            </p>
        </div>
        <h2>This build is triggered by:</h2>
        </div>
        <div class="triggerwrapper">
        <?php
        $object = json_decode($this->build["buildCause"]);

        self::$projectPath = $this->build["projectPath"];
        $cause = V2BuildCause::unserialize($object);
        $cause->echoHtml();
        self::$projectPath = null;
        ?>
        </div>
            <div class="lintcontent">
        <h2>Lint <?php EmbedUtils::displayAnchor("lint") ?></h2>
        <?php
        if(count($this->lint->statuses) === 0) {
            echo '<p>All OK! :) Poggit Lint detected no problems in this build.</p>';
        }else{
            foreach($this->lint->statuses as $status) {
                ?>
                <div class="brief-info">
                    <!-- <?= get_class($status) ?> -->
                    <p class='remark'>Severity: <?=BuildResult::$names[$status->level] ?></p>
                    <?php $status->echoHtml() ?>
                </div>
                <?php
            }
        }
        echo "</div></div>";
    }

    public function og() {
        $c = date(DATE_ISO8601, $this->build["buildCreation"]);
        echo "<meta property='article:published_time' content='$c'/>";
        echo "<meta property='article:author' content='$this->ownerName'/>";
        echo "<meta property='article:section' content='CI'/>";
        return ["article", $this->permLink];
    }

    public function getMetaDescription(): string {
        $perm = dechex($this->build["buildId"]);
        return "Poggit CI Build #$this->internalBuildNumber (&$perm) in $this->projectName in {$this->repo->full_name}";
    }
}

// TODO button to promote build to beta or rc

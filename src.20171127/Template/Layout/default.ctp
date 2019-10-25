<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         0.10.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

//$cakeDescription = 'CakePHP: the rapid development php framework';
$cakeDescription = 'WeCollect';
?>
<!DOCTYPE html>
<html>
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= $cakeDescription ?>:
        <?= $this->fetch('title') ?>
    </title>
    <?= $this->Html->meta('icon') ?>

    <?= $this->Html->css('base.css') ?>
    <?= $this->Html->css('cake.css') ?>
    <?= $this->Html->css('kendo.common.min.css') ?>
    <?= $this->Html->css('kendo.metro.min.css') //$this->Html->css('kendo.default.min.css') ?>
    <?= $this->Html->css('kendo.metro.mobile.min') ?>
    <?= $this->Html->css('kendo.custom.css') ?>
    <?= $this->Html->css('wc') //wecollect custom css file ?>

    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
    <?= $this->fetch('script') ?>
    <?= $this->Html->script('jquery.min.js',['async'=>false]) ?>
    <?= $this->Html->script('kendo.all.min.js',['async'=>false]) ?>
</head>
<body>
<!--
    <nav class="top-bar expanded" data-topbar role="navigation">
        <ul class="title-area large-3 medium-4 columns">
            <li class="name">
                <h1><a href=""><?= $this->fetch('title') ?></a></h1>
            </li>
        </ul>
        <div class="top-bar-section">
            <ul class="right">
				<?php if ($this->request->session()->read('Auth.User'))://if ($this->Auth->user()): ?>
                <li>
                    <?= $this->Html->link(__("TRANSACTION LOG"), ["controller" => "TransactionLog","action" => "dateform",]) ?>
                </li>
				<li>
				<?= $this->Html->link(__("BATCH UPLOAD"), ["controller" => "RemittanceBatch","action" => "upload",]) ?>
				</li>
				<li>
				<?= $this->Html->link(__("BATCH SEARCH"), ["controller" => "RemittanceBatch","action" => "index",]) ?>
				</li>
				<li>
				<?= $this->Html->link(__("LOGOUT"),["controller" => "Users","action" => "logout",])?>
				</li>
				<?php else: ?>
				<li>
				<?= $this->Html->link(__("LOGIN"),["controller" => "Users","action" => "login",])?>
				</li>
				<?php endif; ?>
            </ul>
        </div>
    </nav>
-->
<!-- responsive panel -->
<nav id="sidebar">
    <div id="topbanner">
        <?=   $this->Html->link($this->Html->image('wc_logo_230px.png', ['alt' => 'top banner']),["controller" => "Pages","action" => "display",'index'],['escape' => false]); ?>
    </div>
    <?php if ($this->request->session()->read('Auth.User'))://if ($this->Auth->user()): ?>
    <div class="demo-section k-content" id="menuBox" onclick="">
<ul id="menu">
<li>
    Settlement
    <ul>
        <li><?= $this->Html->link(__("Transaction Search"),["controller" => "SettlementTransaction","action" => "index",])?>
        </li>
        <li>
            <?=__("Settlement Process")?>
            <ul>
                <li><?= $this->Html->link(__("Processor Reconciliation"),["controller" => "Reconciliation","action" => "index",])?>
                </li>
                <li><?= $this->Html->link(__("Processor Reconciliation Search"),["controller" => "Reconciliation","action" => "search",])?>
                </li>
                <li><?= $this->Html->link(__("FX Rate Update"),["controller" => "SettlementRate","action" => "batchUpdate",])?>
                </li>
            </ul>
        </li>
        <li><?= $this->Html->link(__("Aggregated Data Download"),["controller" => "TransactionLog","action" => "dateform",])?>
        </li>
        <li><?= $this->Html->link(__("Gateway Excel Upload"),["controller" => "TransactionLog","action" => "upload"])?>
        </li>
    </ul>
</li>
<li>
    Remittance
    <ul>
        <li><?= $this->Html->link(__("Batch Upload"),["controller" => "RemittanceBatch","action" => "upload",])?>
        </li>
        <li><?= $this->Html->link(__("Batch Search"),["controller" => "RemittanceBatch","action" => "search",])?>
        </li>
        <li><?= $this->Html->link(__("Batch Transaction Search"),["controller" => "RemittanceBatch","action" => "searchTx",])?>
        </li>
        <li><?= $this->Html->link(__("Instant Transaction Search"),["controller" => "RemittanceBatch","action" => "searchInstant",])?>
        </li>
        <li><?= $this->Html->link(__("Merchant Balance"),["controller" => "MerchantTransaction","action" => "index",])?>
        </li>
        <li><?= $this->Html->link(__("Instant Transaction Setup"),["controller" => "MerchantTransaction","action" => "setup",])?>
        </li>
        <li>
            Risk Filter Setup
            <ul>
                <li><?= $this->Html->link(__("Blacklist Filter"),["controller" => "RemittanceFilter","action" => "blacklist",])?>
                </li>
                <li><?= $this->Html->link(__("Transaction Amount Limit Filter"),["controller" => "RemittanceFilter","action" => "txLimit",])?>
                </li>
                <li><?= $this->Html->link(__("Moving Sum Transaction Amount Limit Filter"),["controller" => "RemittanceFilter","action" => "sumLimit",])?>
                </li>
                <li><?= $this->Html->link(__("Transaction Rate Limit Filter"),["controller" => "RemittanceFilter","action" => "rateLimit",])?>
                </li>
            </ul>
        </li>
    </ul>
</li>
    <li>
        Identity Verification
        <ul>
            <li><?= $this->Html->link(__("Usage Report"),["controller" => "CivsReport","action" => "dateform",])?>
            </li>
        </ul>
    </li>
<li>
    <?=__('Tools')?>
    <ul>
        <li><?= $this->Html->link(__("ChinaGPay Transaction Status Update"),['controller' => 'ChinaGPay', 'action'=>'index']); ?>
        </li>
    </ul>
</li>
<li>
    <?=__('Setup')?>
    <ul>
        <li><?= $this->Html->link(__("Merchant Account Search"),["controller" => "Merchants","action" => "search",])?>
        </li>
        <li><?= $this->Html->link(__("Add Merchant Account"),["controller" => "Merchants","action" => "add",])?>
        </li>
        <li><?= $this->Html->link(__("Master Merchant List"),["controller" => "Merchants","action" => "listGroup",])?>
        </li>
        <li><?= $this->Html->link(__("Public Holiday"),['controller' => 'Holidays', 'action'=>'index']); ?>
        </li>
        <li><?= $this->Html->link(__("Settlement FX Rate Symbol"),['controller' => 'SettlementRate', 'action'=>'index']); ?>
        </li>
    </ul>
</li>
<li>
    Information
    <ul>
        <li><?= $this->Html->link(__("News"),['controller' => 'MerchantArticle', 'action'=>'index', 'news']); ?>
        </li>
        <li><?= $this->Html->link(__("Documentation"),['controller' => 'MerchantArticle', 'action'=>'index']); ?>
        </li>
    </ul>
</li>
<li>
    Admin
    <ul>
        <li><?= $this->Html->link(__("Change Password"),["controller" => "Users","action" => "update",])?>
        </li>
        <li><?= $this->Html->link(__("Add User"),["controller" => "Users","action" => "add",])?>
        </li>
    </ul>
</li>
<li>
    <?php if ($this->request->session()->read('Auth.User'))://if ($this->Auth->user()): ?>
    <?= $this->Html->link(__("Logout"),["controller" => "Users","action" => "logout",])?>
    <?php else: ?>
    <?= $this->Html->link(__("Login"),["controller" => "Users","action" => "login",])?>
    <?php endif; ?>
</li>
</ul>
</div>
    <?php endif; ?>
</nav>

<script>
$(document).ready(function() {
    $("#menu").kendoMenu({});
    /*
    $("#menu").kendoMenu({orientation: "vertical" });
    $("#sidebar")
            .kendoResponsivePanel({
                breakpoint: 720, //768,
                orientation: "left"
            })
            .on("click", "a", function(e) {
                // handle clicks of dummy items, actual links do not need this
                //alert($(e.target).text() + " clicked");
                $("#sidebar").kendoResponsivePanel("close");
            });
            */
    });
</script>
<style>
    html, body {
        margin: 0;
        padding: 0;
    }
    nav{
        width:90%;
        margin: 0px auto;
    }
    #menuBox {
        max-width: 100%;
        padding-top: 10px;
        /*background: url("<?= $this->Url->image('wc_logo_230px.png', ['alt' => 'logo']); ?>") no-repeat left 0; */
    }
    #content {
        /* clear the floating sidebar */
        overflow: hidden;
        padding-top: 0em;
    }
    nav .k-link{
        font-size: 80%;
    }

</style>
<article id="content">
    <?= $this->Flash->render() ?>
    <div class="container clearfix">
        <?= $this->fetch('content') ?>
    </div>
</article>
    <footer>
    </footer>

</body>
</html>

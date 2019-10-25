<!-- File: src/Template/Pages/upload.ctp -->
<div class="users form">
    <?= $this->Flash->render('auth') ?>
    <?php
            //$this->log($this->request->clientIp(), 'debug');
            $pc_api = new \PayConnectorAPI(FALSE);
            ?>
    <?= $this->Form->create(null, ['type' => 'file', 'url' => ['controller' => 'TransactionLog', 'action' => 'upload']]) ?>
    <fieldset>
        <legend><?= __('Select file to upload') ?></legend>
        <ul class="fieldlist">
            <li>
                <label for=""><span id="pc_label">PayConnector Excel/CSV File</span></label>
                <!-- <label>Last Updated: <?=$pc_api->getPayConnLastTransactionTime() ?></label> -->
                <?= $this->Form->file('pcfile', ['id'=>'pcfile','required' => false, 'accept'=>'text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']) /*  */?>
            </li>
            <li>
                <label for=""><span id="gpay_label">GPay Excel File</span></label>
                <?= $this->Form->file('gpfile', ['id'=>'gpfile','required' => false, 'accept'=>'application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ]) ?>
            </li>
            <li>
                <label for=""><span id="ght_label">GHT File</span></label>
                <?= $this->Form->file('ghfile', ['id'=>'ghfile','required' => false, 'accept'=>'application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ]) ?>
            </li>
            <li>
                <div id="buttonContainer">
                    <?= $this->Form->button(__('Upload'), ['type' => 'submit', 'class'=>'kdbutton', 'data-role'=>"button", 'data-sprite-css-class'=>"k-icon k-i-tick"]); ?>
                    <?= $this->Form->button(__('Reset'), ['type' => 'reset', 'class'=>'kdbutton', 'data-role'=>"button", 'data-icon'=>"cancel"]); ?>
                </div>
            </li>
        </ul>
    </fieldset>
    <div>
    <?php
            if (isset($download_url))
                echo $this->Html->link('Download', $download_url);
    ?>
    </div>
    <div>
        <div id="scheduler"></div>
    </div>

    <?= $this->Form->end() ?>
    <script>
        $(document).ready(function() {
            //$("#upfile").kendoUpload();
            kendo.init("#buttonContainer");
            var dataSource = new kendo.data.SchedulerDataSource({
                transport: {
                    read: "missingRecordJson",
                    dataType: "json",

                },
                schema: {
                    model: {
                        id: "id",
                        fields: {
                            start: { type: "date", from: "start" },
                            end: { type: "date", from: "end" },
                            isAllDay: { type: "boolean", from: "isallday" }
                        }
                    }
                }
            });
            $("#scheduler").kendoScheduler({
                date: new Date(),
                startTime: new Date("2016/10/01 09:00 AM"),
                height: 600,
                views: ["month", "day"],
                editable: false,
                dataSource: dataSource,
                dataBound: function(e) {
                },
                navigate: function(e) {
                    console.log("navigate...", e.date, e.action);
                    //reload button
                    if (e.action == 'changeView') {
                        this.dataSource.read();
                        this.refresh();
                    }
                },
                resources: [
                    {
                        field: "source",
                        dataColorField: "key",
                        dataSource: [
                            { text: "PayConnector", value: 1, key: "#0066ff" },
                            { text: "GPay", value: 2, key: "#f8a398" },
                            { text: "Comparsion", value: 3, key: "#ffff98" }
                        ]
                    }
                ]
            });
            /*
             var scheduler = $("#scheduler").data("kendoScheduler");
             scheduler.refresh();
             */
        });
    </script>
</div>

<div id="footer"> &nbsp;
</div>
<style>
#pc_label{
    color: #0066ff;
}
#gpay_label{
    color: #f8a398;
}
</style>

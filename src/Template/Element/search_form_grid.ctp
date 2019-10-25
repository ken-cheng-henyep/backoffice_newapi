<div class="users form">
    <?= $this->Flash->render('auth') ?>
    <?php
            //$this->log($this->request->clientIp(), 'debug');
    ?>
    <?= $this->Form->create(null, ['url' => ['controller' => 'RemittanceBatch', 'action' => 'index']]) ?>
    <ul class="fieldlist">
        <li>
            <label for="simple-input">Batch ID</label>
            <input type="text" id="batch-id" name="batch_id" class="k-textbox" placeholder="" style="width: 220px;" />
            <?=''/* $this->Form->input('batch_id',['type' => 'text','label'=>false,'id'=>'batch-id' ]) */?>
        </li>
        <li>
            <label for="">Merchant</label>
            <?=  $this->Form->select('merchant',
            $merchant_lst,
            ['empty' => '(choose one)', 'required' => false, 'id'=>'merchantDropdown']); ?>
        </li>
        <li>
            <label for="">Search by</label>
            <input id="timetypeDropdown" name="timetype"/>
        </li>
        <li>
<!--            <label for="">Upload Time</label> -->
            <span>From </span><input id="start" name="start" value="<?= (empty($_REQUEST['start'])?date('Y/m/d',strtotime('-14 day')):$_REQUEST['start']) ?>"/>
            <span>To </span><input id="end" name="end" value="<?= (empty($_REQUEST['end'])?date('Y/m/d'):$_REQUEST['end']) ?>"/>
        </li>
        <li>
            <label for="">Status</label>
            <?=  $this->Form->select(
            'status',
            $status_lst,
            ['empty' => '(choose one)', 'required' => false, 'id'=>'statusDropdown']); ?>
        </li>
        <li>
            <div id="buttonContainer">
                <?= $this->Form->button(__('Search'), ['type' => 'button', 'class'=>'kdbutton', 'data-role'=>"button", 'data-sprite-css-class'=>"k-icon k-i-search", 'onclick'=>'updateGrid()']); ?>
                <?= $this->Form->button(__('Reset'), ['type' => 'reset', 'class'=>'kdbutton', 'data-role'=>"button", 'data-icon'=>"cancel"]); ?>
                <?= $this->Form->button(__('Download'), ['type' => 'button', 'class'=>'kdbutton', 'data-role'=>"button", 'data-sprite-css-class'=>"k-icon k-i-search", 'id'=>'dl-excel', 'onclick'=>'downloadExcel()']); ?>
            </div>
        </li>
    </ul>

    <div></div>
    <?= $this->Form->end() ?>
    <script>
        $(document).ready(function() {
            function startChange() {
                var startDate = start.value(),
                        endDate = end.value();

                if (startDate) {
                    startDate = new Date(startDate);
                    startDate.setDate(startDate.getDate());
                    end.min(startDate);
                } else if (endDate) {
                    start.max(new Date(endDate));
                } else {
                    endDate = new Date();
                    start.max(endDate);
                    end.min(endDate);
                }
            }

            function endChange() {
                var endDate = end.value(),
                        startDate = start.value();

                if (endDate) {
                    endDate = new Date(endDate);
                    endDate.setDate(endDate.getDate());
                    start.max(endDate);
                } else if (startDate) {
                    end.min(new Date(startDate));
                } else {
                    endDate = new Date();
                    start.max(endDate);
                    end.min(endDate);
                }
            }

            var start = $("#start").kendoDatePicker({
                format: "yyyy/MM/dd",
                change: startChange
            }).data("kendoDatePicker");

            var end = $("#end").kendoDatePicker({
                format: "yyyy/MM/dd",
                change: endChange
            }).data("kendoDatePicker");

            start.max(end.value());
            end.min(start.value());

            $("#statusDropdown").kendoDropDownList();
            $("#statusDropdown").data("kendoDropDownList").list.width(300);
            //default search
            $("#statusDropdown").data("kendoDropDownList").value("<?=$default_status?>");
            
            $("#merchantDropdown").kendoDropDownList();
            $("#merchantDropdown").data("kendoDropDownList").list.width(600);
            $("#timetypeDropdown").kendoDropDownList({
                dataSource: [
                    {value:'upload_time', name:"Upload Time"}, {value:'complete_time', name:"Complete Time"}
                ],
                dataTextField: "name",
                dataValueField: "value",
            });
            $("#timetypeDropdown").data("kendoDropDownList").list.width(300);

            /*
             $(".kdbutton").kendoButton({
             spriteCssClass: "k-icon k-edit"
             });
             */
            kendo.init("#buttonContainer");
            $("#dl-excel").hide();
            //console.log('start:'+ $("#start").data("kendoDatePicker").value());

        });

        function onDsChange(cnt) {
            console.log("onDsChange="+cnt);
            if (cnt>0)
                $("#dl-excel").show();
            else
                $("#dl-excel").hide();
        }

        function updateGrid() {
            //update grid
            $('#grid').data("kendoGrid").dataSource.filter( [{ field: "start", operator: "eq", value: $("#start").val()},
                    {field: "end", operator: "lte", value: $("#end").val()},
                    {field: "status", operator: "eq", value: $("#statusDropdown").data("kendoDropDownList").value()},
                    {field: "merchant", operator: "eq", value: $("#merchantDropdown").data("kendoDropDownList").value()},
                    {field: "timetype", operator: "eq", value: $("#timetypeDropdown").data("kendoDropDownList").value()},
                    {field: "batch_id", operator: "eq", value: $("#batch-id").val()},
                    ]);
            $('#grid').data("kendoGrid").dataSource.filter.logic = 'or';

        }

        function downloadExcel() {
            console.log('downloadExcel');
            $.get(
                    '<?=$json_url?>',
                    {"filter[filters]": [
                        { field: "start", operator: "eq", value: $("#start").val()},
                        {field: "end", operator: "lte", value: $("#end").val()},
                        {field: "status", operator: "eq", value: $("#statusDropdown").data("kendoDropDownList").value()},
                        {field: "merchant", operator: "eq", value: $("#merchantDropdown").data("kendoDropDownList").value()},
                        {field: "timetype", operator: "eq", value: $("#timetypeDropdown").data("kendoDropDownList").value()},
                        {field: "batch_id", operator: "eq", value: $("#batch-id").val()}
                    ], "type":"excel"
                    },
                    function( data ) {
                        console.log(data);
                        if (data.status==1 && data.total>0) //success
                            $(location).attr('href', data.path);
                    },
                    'json'
            );
        }

    </script>
</div>
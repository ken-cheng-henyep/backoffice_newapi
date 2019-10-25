<div class="search-input">
    <?= $this->Flash->render('auth') ?>
    <?= $this->Form->create(null, ['url' => ['controller' => 'RemittanceBatch', 'action' => 'index'], 'id'=>'form1']) ?>
    <ul class="fieldlist">
        <li>
            <label for="">Merchant</label>
            <?=  $this->Form->select('merchantid',
            $merchant_lst,
            ['empty' => 'All', 'required' => false, 'id'=>'merchantDropdown']); ?>
        </li>
        <li>
            <label for="">Transaction Time</label>
        </li>
        <li class="search-date">
            <span class="datebox-1"><label for="">From </label><input id="start" name="start" value="<?= (empty($_REQUEST['start'])?date('Y/m/d',strtotime('-14 day')):$_REQUEST['start']) ?>"/></span>
            <span class="datebox-2"><label for="">To </label><input id="end" name="end" value="<?= (empty($_REQUEST['end'])?date('Y/m/d'):$_REQUEST['end']) ?>"/></span>
        </li>
        <li>
            <span>
            <label for="">Transaction Status</label>
            <?=  $this->Form->select(
            'status',
            $status_lst,
            ['empty' => 'All', 'required' => false, 'id'=>'statusDropdown']); ?>
            </span>
            <span class="inputbox-2"><label for="">Transaction ID</label><input id="txid" name="txid" value="<?=$tx_id?>"/></span>
        </li>
        <li>
            <span class="datebox-1"><label for="">Name</label><input id="bname" name="bname" value=""/></span>
            <span class="inputbox-2"><label for="">Account No.</label><input id="account" name="account" value=""/></span>
        </li>
        <li>
            <span class="datebox-1"><label for="">ID Card</label><input id="id_number" name="id_number" value=""/></span>
            <span class="inputbox-2"><label for="">Merchant Ref.</label><input id="merchant_ref" name="merchant_ref" value=""/></span>
        </li>
        <li>
            <span class="datebox-1"><label for="">Test Transaction</label><?=$this->Form->checkbox('test_trans',['id'=>'test_trans']); ?>
            </span>
        </li>

        <li>
            <span>
            <label for="">Processor</label>
                <?=  $this->Form->select(
            'target',
            $target_lst,
            ['empty' => 'All', 'required' => false, 'id'=>'target']); ?>
            </span>
        </li>
        <div id="buttonContainer">
            <?= $this->Form->button(__('Search'), ['type' => 'button', 'class'=>'kdbutton', 'data-role'=>"button", 'data-sprite-css-class'=>"k-icon k-i-search", 'onclick'=>'updateGrid()']); ?>
            <?= $this->Form->button(__('Reset'), ['type' => 'reset', 'class'=>'kdbutton', 'data-role'=>"button", 'data-icon'=>"cancel", 'onclick'=>"$('form')[0].reset()"]); ?>
            <?= $this->Form->button(__('Download'), ['type' => 'button', 'class'=>'kdbutton', 'data-role'=>"button", 'data-sprite-css-class'=>"k-icon k-i-search", 'id'=>'dl-excel', 'onclick'=>'downloadExcel()']); ?>
        </div>
    </ul>
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
            $("#merchantDropdown").kendoDropDownList();
            $("#merchantDropdown").data("kendoDropDownList").list.width(500);
            $("#target").kendoDropDownList();
            $("#target").data("kendoDropDownList").list.width(300);

            /*
             $(".kdbutton").kendoButton({
             spriteCssClass: "k-icon k-edit"
             });
             */
            kendo.init("#buttonContainer");
            $("#dl-excel").hide();

            var form = $("#form1");
            //console.log('start:'+ $("#start").data("kendoDatePicker").value());

            $('input').keypress(function (e) {
                //console.log("keypress");
                if (e.which == 13) {    //enter
                    //$("#form1").submit();
                    updateGrid();
                    e.preventDefault();
                }
            });

        });

        function updateGrid() {
            //update grid
            $('#grid').data("kendoGrid").dataSource.filter([
                {field: "merchant", operator: "eq", value: $("#merchantDropdown").val()},
                {field: "start", operator: "eq", value: $("#start").val()},
                {field: "end", operator: "lte", value: $("#end").val()},
                {field: "status", operator: "eq", value: $("#statusDropdown").data("kendoDropDownList").value()},
                {field: "name", operator: "eq", value: $("#bname").val()},
                {field: "account", operator: "eq", value: $("#account").val()},
                {field: "id_number", operator: "eq", value: $("#id_number").val()},
                {field: "merchant_ref", operator: "eq", value: $("#merchant_ref").val()},

                {field: "target", operator: "eq", value: $("#target").val()},
                {field: "test_trans", operator: "eq", value: ($("#test_trans").is(':checked')?1:0)},
                {field: "txid", operator: "eq", value: $("#txid").val()},
            ]);
            //$('#grid').data("kendoGrid").dataSource.filter.logic = 'or';
        }

        function downloadExcel() {
            console.log("downloadExcel now");
            $.get(
                    'instant-json',
                    {"filter[filters]": [
                        {field: "merchant", operator: "eq", value: $("#merchantDropdown").val()},
                        {field: "start", operator: "eq", value: $("#start").val()},
                        {field: "end", operator: "lte", value: $("#end").val()},
                        {field: "status", operator: "eq", value: $("#statusDropdown").data("kendoDropDownList").value()},
                        {field: "name", operator: "eq", value: $("#bname").val()},
                        {field: "account", operator: "eq", value: $("#account").val()},
                        {field: "id_number", operator: "eq", value: $("#id_number").val()},
                        {field: "merchant_ref", operator: "eq", value: $("#merchant_ref").val()},
                        {field: "target", operator: "eq", value: $("#target").val()},
                        {field: "test_trans", operator: "eq", value: ($("#test_trans").is(':checked')?1:0)},
                        {field: "txid", operator: "eq", value: $("#txid").val()},
                    ], "type":"excel"
                    },
                    function( data ) {
                        //console.log(data);
                        if (data.status==1 && data.total>0) //success
                            $(location).attr('href', data.path);
                    },
                    'json'
            );
        }

    </script>
</div>

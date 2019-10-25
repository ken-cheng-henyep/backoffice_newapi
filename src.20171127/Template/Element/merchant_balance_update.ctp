<h3><?= __('Merchant Balance Update') ?></h3>
<div class="users form">
<div id="cfdialog"></div>
<div id="idialog"></div>
<?= $this->Form->create(null, ['id'=>'updateform',]) ?>
<fieldset>
    <ul class="fieldlist">
        <!--
        <li>
            <label for="simple-input">Currency</label>
            <?= $this->Form->input('symbol',['type' => 'text','label'=>false,'id'=>'symbol', 'readonly' => true, 'value'=>'' ]) ?>
        </li>
        -->
        <li id="amount">
            <label for="simple-input">Amount</label>
            <span id="symbol"></span>&nbsp;<input id="amt" name="amt" required="true"/>
        </li>
        <li>
            <label for="simple-input">Remarks</label>
            <?= $this->Form->input('remarks',['type' => 'text','label'=>false,'id'=>'remarks', 'required' => true, 'value'=> '' ]) ?>
        </li>
        <li>
            <div id="buttonContainer">
                <?= $this->Form->button(__('Update'), ['type' => 'button', 'id'=>'update_btn', 'class'=>'kdbutton', 'href'=>"#", 'onclick'=>'confirm();', 'data-role'=>"button", 'data-sprite-css-class'=>"k-icon k-i-tick"]); ?>
            </div>
        </li>
    </ul>
</fieldset>

</div>
<script>
$(document).ready(function() {
    // info dialog
    $("#idialog").kendoDialog({
        width: 320,
        height: 260,
        title: "WeCollect",
        visible: false,
        actions: [{
            text: "OK",
            action: function(e){
                // e.sender is a reference to the dialog widget object
                // OK action was clicked
                // Returning false will prevent the closing of the dialog
                return true;
            },
            primary: true
        }],
    }).data("kendoDialog");

    // confirm dialog
    $("#cfdialog").kendoDialog({
        width: 320,
        height: 260,
        title: "WeCollect",
        visible: false,
        actions: [
            { text: "OK",
            action: function(e){
                // e.sender is a reference to the dialog widget object
                updateBal();
                // Returning false will prevent the closing of the dialog
                return true;
            },
                primary: true
            }, { text: 'Cancel' }
        ],
    }).data("kendoDialog");

    $('#amt').kendoNumericTextBox({
        value: null,
        min: -10000000,
        max: 10000000,
        step: 100,
        format: "n2",
        decimals: 2,
        //change: onTBChange,
        //spin: onTBChange
    });

    console.log("ready");
});

function confirm() {
    var dialog = $("#cfdialog").data("kendoDialog");
    var mid = $("#merchantDropdown").data("kendoDropDownList").value();
    var amt= $("#amt").val();
    var rmk= $("#remarks").val();

    console.log("amt:"+amt+", mid:"+mid);

    if (amt=="" || rmk=="")
        return false;
    dialog.content("Confirm balance update with "+amt+" ?");
    dialog.open();
}

function updateBal() {
    //event.preventDefault();

    var url= "<?=$update_url?>";
    var dialog = $("#idialog").data("kendoDialog");

    var mid = $("#merchantDropdown").data("kendoDropDownList").value();
    var wid = $("#walletDropdown").data("kendoDropDownList").value();
    var amt= $("#amt").val();
    var rmk= $("#remarks").val();

    console.log("amt:"+amt+", mid:"+mid+", wallet:"+wid);

    if (amt=="" || rmk=="")
        return false;

    $.post(url,'mid='+mid+'&wallet_id='+wid+'&amt='+amt+'&remarks='+rmk, function(data) {
        console.log("return:"+data.status);
        if (data.status==1) {
            //clear amt
            //$("#amt").val("");
            $("#amt").data("kendoNumericTextBox").value("");
            //$('#grid').data('kendoGrid').dataSource.read();
            //return true;
            updateGrid();
            dialog.content("Balance updated with "+data.amount);
            dialog.open();
        }
        //if (status=='fail')
        //location.reload();
    }, 'json');

    //console.log('end post');
    return false;
}
</script>
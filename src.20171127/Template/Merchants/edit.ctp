<div class="remittanceBatch index large-12 medium-10 columns content">
    <h3><?= __('Edit Merchant') ?></h3>
    <div class="users form">
        <?= $this->Flash->render('auth') ?>
        <?= $this->Form->create(null, ['id'=>"mform",'url' => ['controller' => 'Merchants', 'action' => 'edit', $merchant['id'] ]]) ?>
        <ul class="fieldlist">
            <li>
                <label for="">Master Merchant:</label> <?=$merchant['group_name']?>
            </li>
            <li>
                <label for="simple-input">Merchant Name:</label>
                <input type="text" id="name" name="name" class="k-textbox" placeholder="" style="width: 400px;" required="true" data-fieldname="Merchant Name" value="<?=$merchant['name']?>"/>
            </li>
            <li>
                <label for="simple-input">Merchant ID:</label>
                <input type="text" id="mid" name="mid" class="k-textbox" readonly="true" style="width: 220px;" required="true" data-fieldname="Merchant ID" value="<?=$merchant['id']?>"
                       data-available="true" data-available-msg="Merchant ID has already been used"/>
            </li>
            <li>
                <label for="">Account Type:</label>
                <input id="actypeDropdown" name="processor_account_type"/>
            </li>
            <li>
                <span>Enabled </span><?= $this->Form->checkbox('enabled',['checked'=>($merchant['enabled']>0)])?>
            </li>

        </ul>
        <div>&nbsp;</div>

        <div id="tabstrip">
            <ul>
                <li>Settlement</li>
                <li>Remittance</li>
            </ul>
            <!-- Settlement Tab -->
            <div class="tab-col">
                <div >
                    <span class="tab-col-lbl" for="simple-input">MDR Fee %</span>
                    <input id="percent-mdr_fee" name="settle_fee" placeholder="" style="width: 220px;" required="true" data-fieldname="MDR Fee %" value="<?=$merchant['settle_fee']?>" />
                </div>
                <div >
                    <span class="tab-col-lbl" for="simple-input">MDR Min Fee</span>
                    <input id="numt-mdr_min_fee" name="settle_min_fee_cny" placeholder="" style="width: 220px;" required="true" data-fieldname="MDR Min Fee" value="<?=$merchant['settle_min_fee_cny']?>"/>
                </div>
                <div>
                    <span class="tab-col-lbl" for="simple-input">Refund Fee</span>
                    <input id="numt-refund_fee" name="refund_fee_cny" style="width: 220px;" required="true" data-fieldname="Refund Fee" value="<?=$merchant['refund_fee_cny']?>" />
                </div>
                <div>
                    <span class="tab-col-lbl" for="simple-input">FX Package</span>
                    <input id="fx-package-ddown" name="settle_option" value="<?=$merchant['settle_option']?>"/>
                </div>
                <!-- For package 2 merchant -->
                <div>
                    <span class="tab-col-lbl" for="simple-input">Settlement Rate Symbol</span>
                    <input id="settle-rate-symbol-ddown" name="settle_rate_symbol" value="<?=$merchant['settle_rate_symbol']?>"/>
                </div>
                <div>
                    <span class="tab-col-lbl" for="simple-input">Rounding Precision</span>
                    <input id="rounding-ddown" name="round_precision" value="<?=$merchant['round_precision']?>"/>
                </div>
                <div>
                    <span class="tab-col-lbl" for="simple-input">Processor Settlement Currency</span>
                    <input id="pr-settle-ddown" name="processor_settle_currency"/>
                </div>
                <div>
                    <span class="tab-col-lbl" for="simple-input">FX source</span>
                    <input id="fx-source-ddown" name="fx_source"/>
                </div>
                <div class="is_master">
                    <span class="tab-col-lbl" for="simple-input">Settlement Currency</span>
                    <input id="settle-symbol-ddown" name="settle_currency"/>
                </div>
                <div class="is_master">
                    <span class="tab-col-lbl" for="simple-input">Settlement Handling Fee</span>
                    <input id="numt-settle-handle-fee" name="settle_handling_fee" required="true" data-fieldname="Settlement Handling Fee" value="<?=$merchant['settle_handling_fee']?>"/>
                </div>
                <div class="is_master tab-row">
                    <span class="tab-col-lbl" for="simple-input">Report Recipient Email (separated by , or ;)</span>
                <textarea id="recipient-email" name="recipient_email" rows="3" cols="8" type="textarea" multiple="true" data-multipleemails-msg="Please enter email list correctly."><?=$merchant['report_recipient_email']?></textarea>
                </div>
            </div>
            <!-- Remittance Tab -->
            <div class="tab-col">
                <div class="is_master">
                    <span class="tab-col-lbl" for="simple-input">Remittance Rate Symbol</span>
                    <input id="rm-symbol-ddown" name="remittance_symbol" value="<?=$merchant['remittance_symbol']?>"/>
                </div>
                <div class="is_master">
                    <span class="tab-col-lbl" for="simple-input">Cross Border Remittance Fee %</span>
                    <input id="percent-remittance-fee" name="remittance_fee" data-fieldname="Cross Border Remittance Fee %" value="<?=$merchant['remittance_fee']?>"/>
                </div>
                <div class="is_master">
                    <span class="tab-col-lbl" for="simple-input">Cross Border Remittance Min Fee</span>
                    <input id="numt-remittance-min-fee" name="remittance_min_fee" data-fieldname="Cross Border Remittance Min Fee" value="<?=$merchant['remittance_min_fee']?>"/>
                </div>
                <div class="is_master">
                    <span class="tab-col-lbl" for="simple-input">Fee Bearer</span>
                    <input id="remittance_fee_type" name="remittance_fee_type" value="<?=$merchant['remittance_fee_type']?>"/>
                </div>
                <div class="is_master">
                    <span class="tab-col-lbl" for="simple-input">Local Remittance Enabled</span>
                    <?= $this->Form->checkbox('local_remittance_enabled',["id"=>"local_remittance_enabled", 'checked'=>"{$merchant['local_remittance_enabled']}"])?>
                </div>
                <div class="is_master">
                    <span class="tab-col-lbl" for="simple-input">Local Remittance Fee</span>
                    <input id="numt-local-remittance-fee" name="local_remittance_fee" data-fieldname="Local Remittance Fee" value="<?=$merchant['local_remittance_fee']?>"/>
                </div>
                <div class="is_master">
                    <span class="tab-col-lbl" for="simple-input">Pre-authorized Remittance</span>
                    <?= $this->Form->checkbox('remittance_preauthorized',['checked'=>"{$merchant['remittance_preauthorized']}"])?>
                </div>
                <div class="is_master">
                    <span class="tab-col-lbl" for="simple-input">Remittance API Enabled</span>
                    <?= $this->Form->checkbox('remittance_api_enabled',['checked'=>"{$merchant['remittance_api_enabled']}"])?>
                </div>
                <div class="is_master">
                    <span class="tab-col-lbl" for="simple-input">Skip Balance Check</span>
                    <?= $this->Form->checkbox('skip_balance_check',['checked'=>"{$merchant['skip_balance_check']}"])?>
                </div>
                <div class="is_master">
                    <span class="tab-col-lbl" for="simple-input">Remittance Netting</span>
                    <?= $this->Form->checkbox('remittance_netting',['checked'=>"{$merchant['remittance_netting']}"])?>
                </div>
            </div>
        </div>


        <div id="buttonContainer">
            <?= $this->Form->button(__('Save'), ['type' => 'button', 'class'=>'kdbutton', 'data-role'=>"button", 'data-sprite-css-class'=>"k-icon k-i-search", 'onclick'=>'sendForm()']); ?>
            <?= $this->Form->button(__('Reset'), ['type' => 'reset', 'class'=>'kdbutton', 'data-role'=>"button", 'data-icon'=>"cancel", 'onclick'=>"$('form')[0].reset()"]); ?>
        </div>

        <div>&nbsp;</div>
        <?= $this->Form->end() ?>
        <script>
            $(document).ready(function() {

                $("#actypeDropdown").kendoDropDownList({
                    dataSource: [
                        {value:'1', name:"Online Bank Payment"}, {value:'2', name:"Quick Payment"}, {value:'3', name:"WeChat Pay"}, {value:'4', name:"Alipay"}
                    ],
                    dataTextField: "name",
                    dataValueField: "value",
                });
                $("#actypeDropdown").data("kendoDropDownList").list.width(300);
                $("#actypeDropdown").data("kendoDropDownList").value("<?=$merchant['processor_account_type']?>");
                $("#actypeDropdown").data("kendoDropDownList").readonly();

                $("#rounding-ddown").kendoDropDownList({
                    dataTextField: "text",
                    dataValueField: "text",
                    dataSource: [
                        { text: "2" },
                        { text: "1" },
                    ]
                });
                $("#fx-package-ddown").kendoDropDownList({
                    dataTextField: "text",
                    dataValueField: "value",
                    dataSource: [
                        { text: "Day of transaction", value: "1" },
                        { text: "Day of settlement", value: "2" },
                        { text: "No FX conversion", value: "3" },
                    ]
                });
                $("#fx-package-ddown").data("kendoDropDownList").value("<?=$merchant['settle_option']?>");
                $("#fx-package-ddown").data("kendoDropDownList").readonly();

                $("#pr-settle-ddown").kendoDropDownList({
                    dataTextField: "text",
                    dataValueField: "text",
                    dataSource: [
                        { text: "CNY" },
                        { text: "USD" },
                        { text: "HKD" },
                        { text: "EUR" },
                    ]
                });
                $("#pr-settle-ddown").data("kendoDropDownList").value("<?=$merchant['processor_settle_currency']?>");
                $("#pr-settle-ddown").data("kendoDropDownList").readonly();

                $("#fx-source-ddown").kendoDropDownList({
                    dataTextField: "text",
                    dataValueField: "value",
                    dataSource: [
                        { text: "WeCollect", value: "1" },
                        { text: "WeChat", value: "2" },
                    ]
                });
                $("#fx-source-ddown").data("kendoDropDownList").value("<?=$merchant['fx_source']?>");
                $("#fx-source-ddown").data("kendoDropDownList").readonly();

                $("#remittance_fee_type").kendoDropDownList({
                    dataTextField: "text",
                    dataValueField: "value",
                    dataSource: [
                        { text: "Merchant bears the fee", value: "1" },
                        { text: "Merchants client bears the fee", value: "2" },
                    ]
                });
                $("#settle-symbol-ddown").kendoDropDownList({
                    dataTextField: "text",
                    dataValueField: "text",
                    dataSource: [
                        { text: "USD" },
                        { text: "HKD" },
                        { text: "EUR" },
                        { text: "GBP" },
                    ]
                });
                $("#settle-symbol-ddown").data("kendoDropDownList").value("<?=$merchant['settle_currency']?>");
                $("#settle-symbol-ddown").data("kendoDropDownList").readonly();

                $("#settle-rate-symbol-ddown").kendoDropDownList({
                    dataTextField: "text",
                    dataValueField: "text",
                    dataSource: [
                        { text: "CNYUSD_S1" },
                        { text: "CNYUSD_S2" },
                        { text: "CNYUSD_S3" },
                        { text: "CNYUSD_S4" },
                        { text: "CNYHKD_S1" },
                        { text: "CNYHKD_S2" },
                    ]
                });
                //$("#settle-rate-symbol-ddown").data("kendoDropDownList").value("<?=$merchant['settle_rate_symbol']?>");

                $("#rm-symbol-ddown").kendoDropDownList({
                    dataTextField: "text",
                    dataValueField: "value",
                    dataSource: [
                        { text: "USDR01", value: "USDR01" },
                        { text: "HKDR01", value: "HKDR01" }
                    ]
                });

                $("input[id|='numt']").kendoNumericTextBox({
                    value: null,
                    min: 0,
                    max: 9999,
                    step: 10,
                    format: "n2",
                    decimals: 2,
                    //change: onTBChange,
                });
                $("input[id|='percent']").kendoNumericTextBox({
                    value: null,
                    min: 0,
                    max: 100,
                    step: 1,
                    format: "n2",
                    decimals: 2,
                });
                //Tab
                $("#tabstrip").kendoTabStrip();
                var tabstrip = $("#tabstrip").kendoTabStrip().data("kendoTabStrip");
                //select 1st tab
                tabstrip.select(0);

                var isMaster = false;
                $(".is_master").hide();

                /*
                $("#merchantDropdown").change(onIdChange);
                $("#mid").change(onIdChange);
                */
                onIdChange();
                setLocalRm();

                $('#local_remittance_enabled').click(function() {
                    setLocalRm();
                });

                function setLocalRm() {
                    //var local_rm = this.checked;
                    var local_rm = $("#local_remittance_enabled").is(':checked');
                    console.log("checkLocalRm:"+local_rm+" master:"+isMaster);

                    if (isMaster) {
                        if (local_rm) {
                            $('#numt-local-remittance-fee').prop('required', true);
                            $('#percent-remittance-fee').prop('required', false);
                            $('#numt-remittance-min-fee').prop('required', false);
                        } else {
                            $('#numt-local-remittance-fee').prop('required', false);
                            $('#numt-local-remittance-fee').data("kendoNumericTextBox").value(null);
                            $('#percent-remittance-fee').prop('required', true);
                            $('#numt-remittance-min-fee').prop('required', true);
                        }
                    }
                }
                // check selected master & merchant id
                function onIdChange() {
                    var mid = $("#mid").val();
                    //var masterid = $("#merchantDropdown").data("kendoDropDownList").value();
                    var masterid = "<?=$merchant['group_id']?>";
                    console.log("onIdChange mid="+ mid);
                    console.log("onIdChange master="+ masterid);

                    isMaster = (mid != '' && mid == masterid);
                    //if (mid != '' && mid == masterid) {
                    if (isMaster) {
                        //master merchant
                        $('#numt-settle-handle-fee').prop('required',true);
                        $('#percent-remittance-fee').prop('required',true);
                        $('#numt-remittance-min-fee').prop('required',true);
                        $(".is_master").show();
                    } else {
                        $('#numt-settle-handle-fee').prop('required',false);
                        $('#percent-remittance-fee').prop('required',false);
                        $('#numt-remittance-min-fee').prop('required',false);
                        $(".is_master").hide();
                        $(".is_master input").val(null);
                        $(".is_master textarea").val(null);
                        console.log("reset input");
                    }
                }


            });
            //ready end

            var validator = $("#mform").kendoValidator({
                rules: {
                    multipleemails: function (input) {
                        if (input.is("[data-multipleemails-msg]") && input.val() != "")
                        {
                            var elist = input.val().replace(/\,/g, ";");
                            console.log("email list:"+elist);
                            var emailsArray = elist.split(";");
                            for (var i=0; i < emailsArray.length; i++)
                            {
//console.log("email:"+emailsArray[i]);
//return validateEmail(emailsArray[i].trim());
                                if ((emailsArray[i].trim() != "") && (validateEmail(emailsArray[i].trim()) == false))
                                {
                                    return false;
                                }
                                console.log("email ok:"+emailsArray[i]);
                            }

                        }
                        return true;
                    },

                },
                messages: {
                    required: function(input) {
                        return getRequiredMessage(input);
                    },
                    availability : function(input) {

                        var id = input.attr('id');
                        var msg = kendo.template(input.data('availableMsg') || '');
                        var cache = availability.cache[id];

                        console.log("get available msg:");
                        console.log(cache);
                        if (cache.checking)
                        {
                            return "Checking...";
                        } else {
                            return msg(input.val());

                        }
                    },
                }
            }).data("kendoValidator");

            var availability = {
                cache: {},
                check: function(element, settings) {

                    var id = element.attr('id');
                    var cache = this.cache[id] = this.cache[id] || {};

                    console.log("availability check: "+element.val());
                    console.log(cache);
                    $.ajax({
                        url: settings.url,
                        dataType: 'json',
                        data: { id: element.val() },
                        success: function(data) {
// the `data` object returns true or false
// based on the availability of the value
                            if (data == null || data === "null")
                                cache.valid = true;
                            else
                                cache.valid = false;
                            console.log( "Data Loaded: " + data );

// set the value on the cache object so
// that it can be referenced in the next validation run
                            //cache.valid = data;

                        },

                        failure: function() {
// the ajax call failed so just set the field
// as valid since we don't know for sure that it's not
                            cache.valid = true;
                        },

                        complete: function() {

// trigger validation again
                            validator.validateInput(element);

// cache the inputs value
                            cache.value = element.val();
                        }
                    });
                }
            };

            function validateEmail(email) {
                var re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
                return re.test(email);
            }

            function getRequiredMessage(input) {
                console.log('getRequiredMessage:'+ input.data("fieldname"));
                return "Please enter "+input.data("fieldname");
            }

            function sendForm() {
                console.log("send form");
                $("#mform").submit();
                console.log("submitted form");
            };

            function onDsChange(cnt) {
                console.log("onDsChange="+cnt);
                if (cnt>0)
                    $("#dl-excel").show();
                else
                    $("#dl-excel").hide();
            }

        </script>
    </div>
</div>


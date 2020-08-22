(function ($) {
    new Vue({
        el:'#bravo_space_book_app',
        data:{
            id:'',
            extra_price:[],
            buyer_fees:[],
            message:{
                content:'',
                type:false
            },
            html:'',
            onSubmit:false,
            start_date:'',
            end_date:'',
            start_date_html:'',

            step:1,
            start_date_obj:'',
            allEvents:[],
            number:0,
            max_number:1,
        },
        watch:{
            extra_price:{
                handler:function f() {
                    this.step = 1;
                },
                deep:true
            },
            start_date(){
                this.step = 1;
            },
        },
        computed:{
            total_price:function(){
                var me = this;
                if (me.start_date !== "") {
                    var total_price = 0;
                    var startDate = new Date(me.start_date).getTime();
                    var endDate = new Date(me.end_date).getTime();
                    var isBook = true;
                    for (var ix in me.allEvents) {
                        var item = me.allEvents[ix];
                        var cur_date = new Date(item.start).getTime();
                        if (startDate == endDate) {
                            if (cur_date >= startDate && cur_date <= endDate) {
                                total_price += parseFloat(item.price);
                                if (item.active === 0) {
                                    isBook = false
                                }
                            }
                        } else {
                            if (cur_date >= startDate && cur_date <= endDate) {
                                total_price += parseFloat(item.price);
                                if (item.active === 0) {
                                    isBook = false
                                }
                            }
                        }
                    }

                    total_price = me.number * total_price;

                    var duration_in_hour = moment(endDate).diff(moment(startDate), 'hours');
                    var duration_in_day = moment(endDate).diff(moment(startDate), 'days');
                    for (var ix in me.extra_price) {
                        var item = me.extra_price[ix];
                        var type_total = 0;
                        if (item.enable === true) {
                            switch (item.type) {
                                case "one_time":
                                    type_total += parseFloat(item.price);
                                    break;
                                case "per_hour":
                                        type_total += parseFloat(item.price) * Math.max(duration_in_hour,24);
                                    break;
                                case "per_day":
                                        type_total += parseFloat(item.price) * Math.max(1,duration_in_day) ;
                                    break;
                            }
                            // if (typeof item.per_person !== "undefined") {
                            //     type_total = type_total * me.number;
                            // }
                            total_price += type_total;
                        }
                    }

                    for (var ix in me.buyer_fees) {
                        var item = me.buyer_fees[ix];
                        var type_total = 0;

                        type_total += parseFloat(item.price);

                        if (typeof item.per_person !== "undefined") {
                            type_total = type_total * me.number;
                        }
                        total_price += type_total;
                    }

                    if (isBook === false || me.number === 0) {
                        return 0;
                    } else {
                       return total_price;
                    }
                }
                return 0;
            },
            total_price_html:function(){
                if(!this.total_price) return '';
                return window.bravo_format_money(this.total_price);
            },
            daysOfWeekDisabled(){
                var res = [];

                for(var k in this.open_hours)
                {
                    if(typeof this.open_hours[k].enable == 'undefined' || this.open_hours[k].enable !=1 ){

                        if(k == 7){
                            res.push(0);
                        }else{
                            res.push(k);
                        }
                    }
                }

                return res;
            },
        },
        created:function(){
            for(var k in bravo_booking_data){
                this[k] = bravo_booking_data[k];
            }
        },
        mounted(){
            var me = this;
            var options = {
                // singleDatePicker: true,
                showCalendar: false,
                sameDate: true,
                autoApply           : true,
                disabledPast        : true,
                dateFormat          : bookingCore.date_format,
                enableLoading       : true,
                showEventTooltip    : true,
                classNotAvailable   : ['disabled', 'off'],
                disableHightLight: true,
                minDate:this.minDate,
                opens:'left',
                isInvalidDate:function (date) {
                    for(var k = 0 ; k < me.allEvents.length ; k++){
                        var item = me.allEvents[k];
                        if(item.start == date.format('YYYY-MM-DD')){
                            return item.active ? false : true;
                        }
                    }
                    return false;
                }
            };

            if (typeof  daterangepickerLocale == 'object') {
                options.locale = _.merge(daterangepickerLocale,options.locale);
            }

            this.$nextTick(function () {

                $(this.$refs.start_date).daterangepicker(options).on('apply.daterangepicker',
                    function (ev, picker) {
                        me.start_date = picker.startDate.format('YYYY-MM-DD');
                        me.end_date = picker.endDate.format('YYYY-MM-DD');
                        me.start_date_html = picker.startDate.format(bookingCore.date_format) +' <i class="fa fa-long-arrow-right" style="font-size: inherit"></i> '+ picker.endDate.format(bookingCore.date_format);
                        for(var k = 0 ; k < me.allEvents.length ; k++){
                            var item = me.allEvents[k];
                            if(item.start === picker.endDate.format('YYYY-MM-DD')){
                                me.max_number =  item.number;
                            }
                        }
                    })
                    .on('update-calendar',function (e,obj) {
                        me.fetchEvents(obj.leftCalendar.calendar[0][0], obj.rightCalendar.calendar[5][6])
                    });
            })
        },
        methods:{
            handleTotalPrice:function() {
            },
            fetchEvents(start,end){
                var me = this;
                var data = {
                    start: start.format('YYYY-MM-DD'),
                    end: end.format('YYYY-MM-DD'),
                    id:bravo_booking_data.id,
                    for_single:1
                };

                $.ajax({
                    url: bravo_booking_i18n.load_dates_url,
                    dataType:"json",
                    type:'get',
                    data:data,
                    beforeSend: function() {
                        $('.daterangepicker').addClass("loading");
                    },
                    success:function (json) {
                        me.allEvents = json;
                        var drp = $(me.$refs.start_date).data('daterangepicker');
                        drp.allEvents = json;
                        drp.renderCalendar('left');
                        if (!drp.singleDatePicker) {
                            drp.renderCalendar('right');
                        }
                        $('.daterangepicker').removeClass("loading");
                    },
                    error:function (e) {
                        console.log(e);
                        console.log("Can not get availability");
                    }
                });
            },
            formatMoney: function (m) {
                return window.bravo_format_money(m);
            },
            validate(){
                if(!this.start_date || !this.end_date)
                {
					this.message.status = false;
                    this.message.content = bravo_booking_i18n.no_date_select;
                    return false;
                }
                if(!this.number )
                {
					this.message.status = false;
                    this.message.content = bravo_booking_i18n.no_guest_select;
                    return false;
                }

                return true;
            },
            addNumberType(){
                var me = this;
                if(me.number < parseInt(me.max_number) || !me.max_number) me.number +=1;
            },
            minusNumberType(){
                var me = this;
                if(me.number > 1) me.number -=1;
            },
            doSubmit:function (e) {
                e.preventDefault();
                if(this.onSubmit) return false;

                if(!this.validate()) return false;

                this.onSubmit = true;
                var me = this;

                this.message.content = '';

                if(this.step == 1){
                    this.html = '';
                }

                $.ajax({
                    url:bookingCore.url+'/booking/addToCart',
                    data:{
                        service_id:this.id,
                        service_type:"car",
                        start_date:this.start_date,
                        end_date:this.end_date,
                        extra_price:this.extra_price,
                        number:this.number,
                    },
                    dataType:'json',
                    type:'post',
                    success:function(res){

                        if(!res.status){
                            me.onSubmit = false;
                        }
                        if(res.message)
                        {
                            me.message.content = res.message;
                            me.message.type = res.status;
                        }

                        if(res.step){
                            me.step = res.step;
                        }
                        if(res.html){
                            me.html = res.html
                        }

                        if(res.url){
                            window.location.href = res.url
                        }

                        if(res.errors && typeof res.errors == 'object')
                        {
                            var html = '';
                            for(var i in res.errors){
                                html += res.errors[i]+'<br>';
                            }
                            me.message.content = html;
                        }
                    },
                    error:function (e) {
                        console.log(e);
                        me.onSubmit = false;

                        bravo_handle_error_response(e);

                        if(e.status == 401){
                            $('.bravo_single_book_wrap').modal('hide');
                        }

                        if(e.status != 401 && e.responseJSON){
                            me.message.content = e.responseJSON.message ? e.responseJSON.message : 'Can not booking';
                            me.message.type = false;

                        }
                    }
                })
            },
            openStartDate(){
                $(this.$refs.start_date).trigger('click');
            }
        }

    });

    $('.bravo-video-popup').click(function() {
        let video_url = $(this).data( "src" );
        let target = $(this).data( "target" );
        $(target).find(".bravo_embed_video").attr('src',video_url + "?autoplay=0&amp;modestbranding=1&amp;showinfo=0" );
        $(target).on('hidden.bs.modal', function () {
            $(target).find(".bravo_embed_video").attr('src',"" );
        });
    });


    $(window).on("load", function () {
        var urlHash = window.location.href.split("#")[1];
        if (urlHash &&  $('.' + urlHash).length ){
            var offset_other = 70
            if(urlHash === "review-list"){
                offset_other = 330;
            }
            $('html,body').animate({
                scrollTop: $('.' + urlHash).offset().top - offset_other
            }, 1000);
        }
    });

    $(".bravo-button-book-mobile").click(function () {
        $('.bravo_single_book_wrap').modal('show');
    });

    $(".bravo_detail_space .g-faq .item .header").click(function () {
        $(this).parent().toggleClass("active");
    });

})(jQuery);

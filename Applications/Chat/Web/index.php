<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>workerman-chat PHP聊天室 Websocket(HTLM5/Flash)+PHP多进程socket实时推送技术</title>
    <link href="/css/bootstrap.min.css" rel="stylesheet">
    <link href="/css/jquery-sinaEmotion-2.1.0.min.css" rel="stylesheet">
    <link href="/css/style.css" rel="stylesheet">

    <script type="text/javascript" src="/js/layer.js"></script>
    <script type="text/javascript" src="/js/jquery.min.js"></script>
    <script type="text/javascript" src="/js/jquery-sinaEmotion-2.1.0.min.js"></script>
    <script type="text/javascript" src="/js/swfobject.js"></script>
    <script type="text/javascript" src="/js/web_socket.js"></script>
</head>
<body onload="connect();">
<div class="container">
    <div class="row clearfix">
        <div class="col-md-1 column">
        </div>
        <div class="col-md-3 column">
            <div class="thumbnail">
                <div class="caption" id="userlist"></div>
            </div>
        </div>
        <div class="col-md-6 column">
            <div id="current_client"></div>
            <div class="thumbnail">
                <div class="caption" id="dialog"></div>
            </div>
            <form onsubmit="onSubmit(); return false;">
                <textarea class="textarea thumbnail" id="textarea"></textarea>
                <div class="say-btn">
                    <input type="button" class="btn btn-default face pull-left" value="表情"/>
                    <input type="submit" class="btn btn-default" value="发送"/>
                </div>
            </form>
            <p class="cp">PHP多进程+Websocket(HTML5/Flash)+PHP Socket实时推送技术&nbsp;&nbsp;&nbsp;&nbsp;Powered by
                <a href="http://www.workerman.net/workerman-chat" target="_blank">workerman-chat</a></p>
        </div>
    </div>
</div>
<script type="text/javascript">
    // 如果浏览器不支持websocket，会使用这个flash自动模拟websocket协议，此过程对开发者透明
    WEB_SOCKET_SWF_LOCATION = "/swf/WebSocketMain.swf";
    // 开启flash的websocket debug
    WEB_SOCKET_DEBUG = true;
    var ws, name, service_id, service_name, client_id, client_name;

    $(function () {
        Date.prototype.format = function(fmt) {
            var o = {
                "M+" : this.getMonth()+1,                 //月份
                "d+" : this.getDate(),                    //日
                "h+" : this.getHours(),                   //小时
                "m+" : this.getMinutes(),                 //分
                "s+" : this.getSeconds(),                 //秒
                "q+" : Math.floor((this.getMonth()+3)/3), //季度
                "S"  : this.getMilliseconds()             //毫秒
            };
            if(/(y+)/.test(fmt)) {
                fmt=fmt.replace(RegExp.$1, (this.getFullYear()+"").substr(4 - RegExp.$1.length));
            }
            for(var k in o) {
                if(new RegExp("("+ k +")").test(fmt)){
                    fmt = fmt.replace(RegExp.$1, (RegExp.$1.length==1) ? (o[k]) : (("00"+ o[k]).substr((""+ o[k]).length)));
                }
            }
            return fmt;
        };

        $('.face').click(function (event) {
            $(this).sinaEmotion();
            event.stopPropagation();
        });
    });

    // 连接服务端
    function connect() {
        // 创建websocket
        ws = new WebSocket("ws://" + document.domain + ":7272");
        // 当socket连接打开时，输入用户名
        ws.onopen = onOpen;
        // 当有消息时根据消息类型显示不同信息
        ws.onmessage = onMessage;
        ws.onclose = function () {
            console.log("连接关闭，定时重连");
            connect();
        };
        ws.onerror = function () {
            console.log("出现错误");
        };
    }

    // 连接建立时发送登录信息
    function onOpen() {
        if (!name) {
            showPrompt();
        }
        // 登录
        var loginData = JSON.stringify({
            type: 'client_login',
            client_name: name.replace(/"/g, '\\"')
        });
        console.log("websocket握手成功，发送登录数据:" + loginData);
        ws.send(loginData);
    }

    // 服务端发来消息时
    function onMessage(e) {
        console.log(e.data);
        var data = JSON.parse(e.data);
        switch (data.type) {
            case 'ping':
                ws.send('{"type":"pong"}');
                break;
            case 'client_login':
                client_id = data['client_id'];
                client_name = data['client_name'];
                service_id = data['service_id'];
                service_name = data['service_name'];
                if (service_id) {
                    layer.msg('客服：'+service_name+' 为你服务');
                } else {
                    layer.msg('尚无客服在线');
                }
                console.log(client_name + "登录成功");
                break;
            case 'service_login':
                service_id = data['client_id'];
                service_name = data['client_name'];
                layer.msg('客服' + data.client_name + '上线');
                $('#current_client').text(data.client_name);
                console.log(data['client_name'] + "：客服上线");
                break;
            // 发言
            case 'say':
                say(data['from_client_id'], data['from_client_name'], data['content'], data['time']);
                break;
            // 用户退出 更新用户列表
            case 'logout':
                say(data['from_client_id'], data['from_client_name'], data['from_client_name'] + ' 退出了', data['time']);
                delete client_list[data['from_client_id']];
                break;
            case 'error':
                layer.msg(data.msg);
                break;
        }
    }

    // 输入姓名
    function showPrompt() {
        name = prompt('输入你的名字：', '');
        if (!name || name === null) {
            name = '游客';
        }
    }

    // 提交对话
    function onSubmit() {
        if (!service_id) {
            layer.msg('客服未上线');
            return;
        }

        var input = document.getElementById("textarea");
        var content = input.value.replace(/"/g, '\\"').replace(/\n/g, '\\n').replace(/\r/g, '\\r');
        ws.send(JSON.stringify({
            type: 'say',
            to_client_id: service_id,
            to_client_name: service_name,
            content: content
        }));

        var now = (new Date).format('yyyy-MM-dd hh:mm:ss');
        say(client_id, client_name, content, now);
        input.value = "";
        input.focus();
    }

    function formatContent(content) {
        //解析新浪微博图片
        content = content.replace(/(http|https):\/\/[\w]+.sinaimg.cn[\S]+(jpg|png|gif)/gi, function (img) {
                return "<a target='_blank' href='" + img + "'>" + "<img src='" + img + "'>" + "</a>";
            }
        );

        //解析url
        content = content.replace(/(http|https):\/\/[\S]+/gi, function (url) {
                if (url.indexOf(".sinaimg.cn/") < 0) {
                    return "<a target='_blank' href='" + url + "'>" + url + "</a>";
                } else {
                    return url;
                }
            }
        );

        return content;
    }

    // 发言
    function say(from_client_id, from_client_name, content, time) {
        content = formatContent(content);

        var info = '<div class="speech_item">' +
            '<img src="http://lorempixel.com/38/38/?' + from_client_id + '" class="user_icon" /> ' + from_client_name + ' <br> ' + time + '<div style="clear:both;"></div>' +
            '<p class="triangle-isosceles top">' + content + '</p> ' +
            '</div>';
        $("#dialog").append(info).parseEmotion();
    }
</script>
</body>
</html>

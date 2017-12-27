<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>workerman 简单客服IM demo</title>
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
            <div class="thumbnail">
                <div class="caption" id="dialog"></div>
            </div>
            <form onsubmit="onSubmit(); return false;">
                <select style="margin-bottom:8px" id="client_list">
                    <option value="none">请选择用户</option>
                </select>
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
    var ws, name, client_list = {}, select_client_id = 'none';

    $(function () {
        $("#client_list").change(function () {
            select_client_id = $("#client_list option:selected").attr("value");
        });
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
            type: 'service_login',
            client_name: name.replace(/"/g, '\\"')
        });
        console.log("websocket握手成功，发送登录数据:" + loginData);
        ws.send(loginData);
    }

    // 服务端发来消息时
    function onMessage(e) {
        console.log(e.data);
        var data = JSON.parse(e.data);
        switch (data['type']) {
            // 服务端ping客户端
            case 'ping':
                ws.send('{"type":"pong"}');
                break;
            // 登录 更新用户列表
            case 'login':
                //say(data['client_id'], data['client_name'], data['client_name'] + ' 加入了聊天室', data['time']);
                layer.msg(data['client_name'] + ' 刚刚加入咨询');
                if (data['client_list']) {
                    client_list = data['client_list'];
                } else {
                    client_list[data['client_id']] = data['client_name'];
                }
                flushClientList();
                console.log(data['client_name'] + "登录成功");
                break;
            // 发言
            case 'say':
                say(data['from_client_id'], data['from_client_name'], data['content'], data['time']);
                break;
            // 用户退出 更新用户列表
            case 'logout':
                say(data['from_client_id'], data['from_client_name'], data['from_client_name'] + ' 退出了', data['time']);
                delete client_list[data['from_client_id']];
                flushClientList();
        }
    }

    // 输入姓名
    function showPrompt() {
        name = prompt('输入你的名字：', '');
        if (!name || name === null) {
            name = '客服007';
        }
    }

    // 提交对话
    function onSubmit() {
        var input = document.getElementById("textarea");
        ws.send(JSON.stringify({
            type: 'say',
            to_client_id: $("#client_list option:selected").attr("value"),
            to_client_name: $("#client_list option:selected").text(),
            content: input.value.replace(/"/g, '\\"').replace(/\n/g, '\\n').replace(/\r/g, '\\r')
        }));
        input.value = "";
        input.focus();
    }

    // 刷新用户列表框
    function flushClientList() {
        var userlist_window = $("#userlist");
        var client_list_select = $("#client_list");
        userlist_window.empty();
        client_list_select.empty();
        userlist_window.append('<h4>在线咨询用户</h4><ul>');
        client_list_select.append('<option value="none" id="cli_all">请选择用户</option>');
        for (var p in client_list) {
            userlist_window.append('<li id="' + p + '">' + client_list[p] + '</li>');
            client_list_select.append('<option value="' + p + '">' + client_list[p] + '</option>');
        }
        client_list_select.val(select_client_id);
        userlist_window.append('</ul>');
    }

    // 发言
    function say(from_client_id, from_client_name, content, time) {
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

        var info = '<div class="speech_item">' +
            '<img src="http://lorempixel.com/38/38/?' + from_client_id + '" class="user_icon" /> ' + from_client_name + ' <br> ' + time + '<div style="clear:both;"></div>' +
            '<p class="triangle-isosceles top">' + content + '</p> ' +
            '</div>';
        $("#dialog").append(info).parseEmotion();
    }
</script>
</body>
</html>

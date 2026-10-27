@if($live && $project)
@if(in_array($screen,['guided','brief']))@include('marketing.workspace.conversation-history')@endif
@if(!($conversationReady??false))<div class="ws-ai-paused" role="status"><i class="bi bi-pause-circle"></i><span><strong>AI对话暂未开放继续调用</strong><br>请检查AI Connect连接或本地测试开关。可查看历史和先写草稿；未发送的文字保留在输入框。</span></div>@endif
<form data-project-chat-form data-inline="{{ in_array($screen,['guided','brief'])?'1':'0' }}"><div class="ws-composer"><i class="bi bi-chat-dots"></i><input type="text" name="message" class="form-control" maxlength="2000" required aria-label="项目对话消息" placeholder="告诉AI你想调整什么…"><button type="submit" aria-label="发送项目消息" @disabled(!($conversationReady??false))><i class="bi bi-send"></i></button></div></form>
@else<div class="ws-composer"><i class="bi bi-paperclip"></i><input type="text" class="form-control" aria-label="给AI的建议" placeholder="告诉AI你想调整什么…"><button type="button" data-notice aria-label="发送建议"><i class="bi bi-send"></i></button></div>@endif

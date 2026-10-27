<section class="ws-sample-assistant" aria-label="项目策划对话样本">
    <div class="d-flex align-items-center gap-2 mb-3"><span class="ws-ai-avatar">AI</span><div><h2 class="mb-0">AI策划助手</h2><small class="text-secondary">同一项目 · 连续对话</small></div><button type="button" class="btn btn-sm btn-light ms-auto" data-sample-reset aria-label="重置对话样本"><i class="bi bi-arrow-counterclockwise"></i></button></div>
    <div class="ws-sample-task"><i class="bi bi-chat-dots"></i> <span data-sample-task>{{ $screen==='guided'?'一起补齐策划摘要':'讨论当前创意，其他步骤仍保留上下文' }}</span></div>
    <div data-sample-log role="log" aria-live="polite" aria-label="对话消息"></div>
    <div data-sample-quick class="ws-suggestions my-3">
        @if($screen==='guided')
        <button type="button" data-sample-say="我希望获得询价">获得询价</button><button type="button" data-sample-say="可以给我建议吗？">请AI建议</button><button type="button" data-sample-say="主要给第一次选购货柜的人看">第一次选购的客户</button>
        @else
        <button type="button" data-sample-say="第二个故事不要那么正式，像老板和客户聊天">第二个故事更亲切</button><button type="button" data-sample-say="这会影响哪些内容？">会影响什么？</button>
        @endif
    </div>
    <form data-sample-form><div class="ws-composer"><i class="bi bi-chat-dots"></i><input class="form-control" name="message" maxlength="1000" required placeholder="回答、提问或请AI给建议…" aria-label="样本对话消息"><button type="submit" aria-label="发送样本消息"><i class="bi bi-send"></i></button></div></form>
    <div class="ws-note mt-2" role="status" data-sample-feedback>预设脚本，不调用AI；演示记录仅留在本标签页，不写入项目。</div>
    @if($screen==='guided')<button class="btn ws-primary w-100 mt-3" type="button" data-sample-review>检查并确认摘要 <i class="bi bi-arrow-right"></i></button>@endif
</section>

<section id="sb-save-scope" hidden>
    <p class="quiet small">选择这次要更新的内容，至少保留一项。</p>
    <div class="sb-scope-cards">
        <label class="sb-scope-card"><span class="sb-scope-top">图片 <input type="checkbox" id="sb-scope-images" checked></span><span class="sb-scope-preview"><img id="sb-scope-image" alt="当前分镜图片参考"><i id="sb-scope-image-placeholder" class="bi bi-image" hidden></i></span><strong>更新对应画面</strong><small>默认由 AI 判断受影响的首帧、尾帧和补充帧。</small></label>
        <label class="sb-scope-card"><span class="sb-scope-top">导演稿与分镜资料 <input type="checkbox" id="sb-scope-director" checked></span><span class="sb-scope-preview"><span class="sb-scope-paper">第1段 · 导演设计<span></span><span></span><span></span></span></span><strong>更新故事与设计</strong><small>整理主要信息、场景、动作、口播和时间安排。</small></label>
    </div>
    <details id="sb-scope-frames"><summary>选择要更新的图片 · 默认 AI 判断</summary><label><input type="checkbox" id="sb-scope-auto" checked> AI 判断受影响图片</label><div id="sb-scope-frame-options" hidden><label><input type="checkbox" value="start_frame" checked> 首帧</label><label><input type="checkbox" value="end_frame" checked> 尾帧</label><label><input type="checkbox" value="extra_image" checked> 补充帧</label></div></details>
    <div class="inset-note"><i class="bi bi-info-circle"></i><p id="sb-scope-note"></p></div>
</section>
<section id="sb-timing-choice" hidden>
    <p class="sb-timing-warning">目标时长偏紧，先决定怎样把话讲清楚。</p>
    <p class="quiet small" id="sb-timing-original"></p>
    <label class="sb-scope-card"><span><input type="radio" name="sb-timing-resolution" value="shorten" checked> 精简表达，保留目标时长</span><small>请填写并确认精简口播，系统按这份文字重新安排；不要删掉必要条件或添加商品事实。</small><textarea id="sb-short-speech" maxlength="4000" rows="2" aria-label="确认精简后的口播" placeholder="填写你确认的精简口播"></textarea></label>
    <label class="sb-scope-card"><span><input type="radio" name="sb-timing-resolution" value="keep"> 保留完整信息，采用建议时长</span><small id="sb-timing-suggestion"></small></label>
    <p class="quiet small">本阶段为设计时长，实际配音和视频能力将在 Step 4 核对。</p>
</section>

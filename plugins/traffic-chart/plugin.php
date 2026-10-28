<?php
/**
 * Plugin Name: 访问折线图
 * Version: 1.0.0
 * Description: 后台用 Canvas 画 24 小时访问量曲线
 * Author: FlyLog
 * Category: 统计
 * Price: free
 * Requires: 10.0
 *
 * 由 FlyLog V10.0 原生插件引擎加载。所有挂接都通过 fly_add_action / fly_add_filter
 * 完成，不修改任何核心文件 —— 停用即完全失效，卸载即无残留。
 */
if (!defined('FLY_BOOTSTRAPPED')) { return; }


// 后台：24 小时访问量折线图（Canvas 手绘，零依赖）
fly_add_filter('fly_admin_menu', function ($nav) {
    $nav['plg_traffic'] = array('访问统计', 'plugins.php?admin_page=traffic_chart', 'view_logs', '');
    return $nav;
});
fly_add_action('fly_admin_head', function () {
    if (($_GET['admin_page'] ?? '') !== 'traffic_chart') { return; }
    $hours = array();
    for ($h = 0; $h < 24; $h++) { $hours[] = (int)fly_cache_get('heat_' . date('Y-m-d') . '_' . sprintf('%02d', $h), 86400); }
    echo '<h3>今日访问量（按小时）</h3><canvas id="flyTraffic" width="720" height="180" style="max-width:100%;border:1px solid var(--border);border-radius:8px"></canvas>'
       . '<script>(function(){var d=' . json_encode($hours) . ',c=document.getElementById("flyTraffic");if(!c)return;'
       . 'var x=c.getContext("2d"),W=c.width,H=c.height,mx=Math.max.apply(null,d.concat([1]));'
       . 'x.clearRect(0,0,W,H);x.strokeStyle="#1f6feb";x.lineWidth=2;x.beginPath();'
       . 'd.forEach(function(v,i){var px=i*(W/24),py=H-10-(v/mx)*(H-30);i?x.lineTo(px,py):x.moveTo(px,py);});x.stroke();'
       . 'x.fillStyle="#9ca3af";x.font="11px sans-serif";for(var i=0;i<24;i+=3){x.fillText(i+"h",i*(W/24),H-2);}})();</script>'
       . '<p class="hint">数据来源：visit-heatmap 插件按小时累计（缓存计数，重启不丢当天数据）。</p>';
});

<?php
// 安全守卫：禁止列目录
http_response_code(403);
exit('Forbidden');

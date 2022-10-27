<?php

namespace Yabx\Ipc;

class Utils {

    public static function seqId(string $entropy = ''): string {
        return microtime(true) . '-' . md5($entropy . mt_rand(10000, 99999));
    }

}

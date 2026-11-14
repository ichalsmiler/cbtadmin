<?php
namespace local_cbtidentitas\privacy;

/** Plugin ini tidak menyimpan data pribadi. */
class provider implements \core_privacy\local\metadata\null_provider {
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}

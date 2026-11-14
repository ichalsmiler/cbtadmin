<?php
namespace local_cbtnama;

use core\hook\output\before_standard_top_of_body_html_generation;

/** Menampilkan nama depan pengguna di samping ikon profil (menu kanan atas). */
class callbacks {
    public static function nama(before_standard_top_of_body_html_generation $hook): void {
        global $USER;

        if (!isloggedin() || isguestuser()) {
            return;
        }
        $nama = json_encode(trim((string) $USER->firstname), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $hook->add_html(
            '<style>#user-menu-toggle .cbt-nama{color:var(--bs-link-color);align-self:center;margin-right:.5rem;'
            . 'max-width:40vw;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}</style>'
            . \html_writer::script(
                "document.addEventListener('DOMContentLoaded',function(){var b=document.querySelector('#user-menu-toggle .userbutton');"
                . "if(b&&!b.querySelector('.cbt-nama')){var s=document.createElement('span');s.className='cbt-nama';"
                . "s.textContent={$nama};b.insertBefore(s,b.firstChild);}});"
            )
        );
    }
}

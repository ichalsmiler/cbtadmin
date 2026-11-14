<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Security\Exceptions\SecurityException;

/**
 * Satu pintu untuk semua halaman panel: cek CSRF, wajib login,
 * batas waktu diam, dan pembatasan role proktor.
 */
class Guard implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('url');
        $path    = trim($request->getPath(), '/');
        $session = session();

        if ($request->is('post')) {
            try {
                service('security')->verify($request);
            } catch (SecurityException $e) {
                return redirect()->back()->with('error', 'Sesi formulir kedaluwarsa. Silakan ulangi.');
            }
        }

        if ($path === 'login') {
            return null;
        }

        if (! $session->get('admin_id')) {
            return $this->deny($request, 'Silakan login terlebih dahulu.');
        }

        // Akun bisa dihapus, dinonaktifkan, atau diganti rolenya saat sesinya masih berjalan.
        $admin = db_connect()->table('admins')->select('role, aktif, nama')
            ->where('id', (int) $session->get('admin_id'))->get()->getRowArray();
        if (! $admin || ! (int) $admin['aktif']) {
            $session->destroy();

            return $this->deny($request, 'Akun Anda sudah tidak aktif.');
        }
        $session->set(['admin_role' => $admin['role'], 'admin_nama' => $admin['nama']]);

        $timeout = (int) env('cbt.sessionTimeout', 3600);
        if (time() - (int) $session->get('last_seen') > $timeout) {
            $session->destroy();

            return $this->deny($request, 'Sesi berakhir karena terlalu lama diam.');
        }
        // Permintaan auto-refresh tidak dihitung sebagai aktivitas.
        if ($path !== 'dashboard/stats') {
            $session->set('last_seen', time());
        }

        if ($session->get('admin_role') !== 'admin' && str_starts_with($path, 'tools')) {
            return redirect()->to(site_url('/'))->with('error', 'Menu Tools hanya untuk administrator.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }

    private function deny(RequestInterface $request, string $message)
    {
        if ($request->isAJAX()) {
            return service('response')->setStatusCode(401)->setJSON(['error' => $message]);
        }

        return redirect()->to(site_url('login'))->with('error', $message);
    }
}

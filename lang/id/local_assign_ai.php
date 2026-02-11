<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package     local_assign_ai
 * @category    string
 * @copyright   2025 Datacurso
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['actions'] = 'Tindakan';
$string['aistatus'] = 'Status AI';
$string['aitaskdone'] = 'Pemrosesan AI selesai. Total kiriman yang diproses: {$a}';
$string['aitaskstart'] = 'Memproses kiriman AI untuk kursus: {$a}';
$string['aitaskuserqueued'] = 'Kiriman dalam antrean untuk pengguna dengan ID {$a->id} ({$a->name})';
$string['altlogo'] = 'Logo Datacurso';
$string['assign_ai:changestatus'] = 'Ubah status persetujuan AI';
$string['assign_ai:review'] = 'Tinjau saran AI untuk tugas';
$string['assign_ai:viewdetails'] = 'Lihat detail komentar AI';
$string['default_rubric_name'] = 'Rubrik';
$string['delayminutes'] = 'Waktu tunggu (menit)';
$string['delayminutes_help'] = 'Jumlah menit yang harus ditunggu setelah siswa memposting sebelum menjalankan peninjauan AI.';
$string['email'] = 'Email';
$string['error_airequest'] = 'Kesalahan saat berkomunikasi dengan layanan AI: {$a}';
$string['errorparsingrubric'] = 'Kesalahan saat mengurai respons rubrik: {$a}';
$string['feedbackcomments'] = 'Komentar';
$string['fullname'] = 'Nama lengkap';
$string['grade'] = 'Nilai';
$string['gradesuccess'] = 'Nilai berhasil dimasukkan';
$string['lastmodified'] = 'Terakhir diubah';
$string['manytasksreviewed'] = '{$a} tugas telah ditinjau';
$string['missingtaskparams'] = 'Parameter tugas hilang. Pemrosesan batch AI tidak dapat dimulai.';
$string['modaltitle'] = 'Umpan Balik AI';
$string['norecords'] = 'Tidak ada catatan ditemukan';
$string['nostatus'] = 'Tidak ada umpan balik';
$string['nosubmissions'] = 'Tidak ada kiriman yang ditemukan untuk diproses.';
$string['notasksfound'] = 'Tidak ada tugas untuk ditinjau';
$string['onetaskreviewed'] = '1 tugas telah ditinjau';
$string['pluginname'] = 'Assignment AI';
$string['privacy:metadata:local_assign_ai_pending'] = 'Menyimpan umpan balik AI yang menunggu persetujuan.';
$string['privacy:metadata:local_assign_ai_pending:approval_token'] = 'Token unik untuk pelacakan persetujuan.';
$string['privacy:metadata:local_assign_ai_pending:assignmentid'] = 'Tugas yang terkait dengan umpan balik AI ini.';
$string['privacy:metadata:local_assign_ai_pending:courseid'] = 'Kursus yang terkait dengan umpan balik ini.';
$string['privacy:metadata:local_assign_ai_pending:grade'] = 'Nilai yang diusulkan oleh AI.';
$string['privacy:metadata:local_assign_ai_pending:message'] = 'Pesan umpan balik yang dihasilkan oleh AI.';
$string['privacy:metadata:local_assign_ai_pending:rubric_response'] = 'Umpan balik rubrik yang dihasilkan oleh AI.';
$string['privacy:metadata:local_assign_ai_pending:status'] = 'Status persetujuan umpan balik.';
$string['privacy:metadata:local_assign_ai_pending:title'] = 'Judul umpan balik yang dihasilkan.';
$string['privacy:metadata:local_assign_ai_pending:userid'] = 'Pengguna yang menerima umpan balik AI.';
$string['processed'] = '{$a} kiriman berhasil diproses.';
$string['processing'] = 'Memproses';
$string['processingerror'] = 'Terjadi kesalahan saat memproses tinjauan AI.';
$string['qualify'] = 'Menilai';
$string['queued'] = 'Semua kiriman telah dikirim ke antrean untuk ditinjau oleh AI. Akan segera diproses.';
$string['reloadpage'] = 'Muat ulang halaman untuk melihat hasil terbaru.';
$string['require_approval'] = 'Tinjau jawaban AI';
$string['review'] = 'Tinjau';
$string['reviewall'] = 'Tinjau semua';
$string['reviewwithai'] = 'Tinjauan dengan AI';
$string['rubricfailed'] = 'Gagal menyuntikkan rubrik setelah 20 percobaan';
$string['rubricmustarray'] = 'Respons rubrik harus berupa array.';
$string['rubricsuccess'] = 'Rubrik berhasil disuntikkan';
$string['save'] = 'Simpan';
$string['saveapprove'] = 'Simpan dan Setujui';
$string['status'] = 'Status';
$string['statusapprove'] = 'Disetujui';
$string['statuspending'] = 'Tertunda';
$string['statusrejected'] = 'Ditolak';
$string['submission_draft'] = 'Draf';
$string['submission_new'] = 'Baru';
$string['submission_none'] = 'Tidak ada kiriman';
$string['submission_submitted'] = 'Dikirim';
$string['submittedfiles'] = 'Berkas dikirim';
$string['task_process_ai_queue'] = 'Proses antrean tertunda Assign AI';
$string['unexpectederror'] = 'Terjadi kesalahan tak terduga: {$a}';
$string['usedelay'] = 'Gunakan peninjauan tertunda';
$string['usedelay_help'] = 'Jika diaktifkan, peninjauan AI akan dijalankan setelah waktu tunggu yang dapat dikonfigurasi, bukan dijalankan segera.';
$string['viewdetails'] = 'Lihat detail';

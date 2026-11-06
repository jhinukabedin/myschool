<?php

namespace Database\Seeders;

use App\Models\SchoolSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'school_name', 'value' => 'EduSphere School', 'group' => 'general'],
            ['key' => 'school_established_year', 'value' => '', 'group' => 'general'],
            ['key' => 'school_tagline', 'value' => 'Knowledge • Character • Excellence', 'group' => 'general'],
            ['key' => 'school_address', 'value' => '12 Education Avenue, Dhanmondi, Dhaka 1205', 'group' => 'general'],
            ['key' => 'school_phone', 'value' => '+880 1700 000000', 'group' => 'general'],
            ['key' => 'school_email', 'value' => 'office@edusphere.test', 'group' => 'general'],
            ['key' => 'school_website', 'value' => 'https://edusphere.test', 'group' => 'general'],
            ['key' => 'currency_symbol', 'value' => '৳', 'group' => 'locale'],
            ['key' => 'currency_code', 'value' => 'BDT', 'group' => 'locale'],
            ['key' => 'locale', 'value' => 'en', 'group' => 'locale'],
            ['key' => 'timezone', 'value' => 'Asia/Dhaka', 'group' => 'locale'],
            ['key' => 'receipt_footer', 'value' => 'This receipt is computer generated. Please retain it for your records.', 'group' => 'fees'],
            ['key' => 'due_reminder_days', 'value' => '7', 'group' => 'fees'],
            ['key' => 'fee_allocation_order', 'value' => 'oldest', 'group' => 'fees'],
            ['key' => 'whatsapp_provider', 'value' => 'none', 'group' => 'whatsapp'],
            ['key' => 'whatsapp_api_url', 'value' => 'https://graph.facebook.com/v20.0', 'group' => 'whatsapp'],
            ['key' => 'whatsapp_token', 'value' => '', 'group' => 'whatsapp'],
            ['key' => 'whatsapp_phone_number_id', 'value' => '', 'group' => 'whatsapp'],
            ['key' => 'whatsapp_template_name', 'value' => 'fee_reminder', 'group' => 'whatsapp'],
            ['key' => 'backup_notes', 'value' => 'Run mysqldump nightly and copy the dump plus storage/app/public to an off-site location.', 'group' => 'system'],
            ['key' => 'website_name_bn', 'value' => 'আদর্শ উচ্চ বিদ্যালয়', 'group' => 'website'],
            ['key' => 'website_name_en', 'value' => 'ADARSHA HIGH SCHOOL', 'group' => 'website'],
            ['key' => 'website_tagline', 'value' => 'শিক্ষা • শৃঙ্খলা • নৈতিকতা • আধুনিকতা', 'group' => 'website'],
            ['key' => 'website_logo_mark', 'value' => 'HS', 'group' => 'website'],
            ['key' => 'website_logo_path', 'value' => '', 'group' => 'website'],
            ['key' => 'website_header_style', 'value' => 'classic', 'group' => 'website'],
            ['key' => 'website_footer_style', 'value' => 'classic', 'group' => 'website'],
            ['key' => 'website_theme', 'value' => 'navy', 'group' => 'website'],
            ['key' => 'website_show_topbar', 'value' => '1', 'group' => 'website'],
            ['key' => 'website_topbar_text', 'value' => 'শিক্ষা • শৃঙ্খলা • নৈতিকতা • আধুনিকতা', 'group' => 'website'],
            ['key' => 'website_header_cta_label', 'value' => 'ভর্তি আবেদন', 'group' => 'website'],
            ['key' => 'website_header_cta_url', 'value' => '/admission/apply', 'group' => 'website'],
            ['key' => 'website_footer_about', 'value' => 'শিক্ষার্থীদের জ্ঞান, নৈতিকতা ও মানবিক মূল্যবোধে গড়ে তোলার অঙ্গীকার।', 'group' => 'website'],
            ['key' => 'website_footer_copyright', 'value' => '© :year আদর্শ উচ্চ বিদ্যালয় • সর্বস্বত্ব সংরক্ষিত', 'group' => 'website'],
            ['key' => 'website_footer_note', 'value' => 'সাইটটি বিদ্যালয়ের অফিস কর্তৃক পরিচালিত।', 'group' => 'website'],
            ['key' => 'website_facebook', 'value' => '', 'group' => 'website'],
            ['key' => 'website_x', 'value' => '', 'group' => 'website'],
            ['key' => 'website_youtube', 'value' => '', 'group' => 'website'],
            ['key' => 'allow_co_teaching', 'value' => '0', 'group' => 'website'],
        ];

        foreach ($settings as $setting) {
            // Never overwrite values an administrator has already configured.
            SchoolSetting::firstOrCreate(['key' => $setting['key']], $setting);
        }

        Cache::forget('school_settings');
    }
}

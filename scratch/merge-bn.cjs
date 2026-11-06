const fs = require('node:fs');
const path = 'lang/bn.json';
const data = JSON.parse(fs.readFileSync(path, 'utf8'));

// Remove the garbled placeholder keys from the earlier draft.
['has_sibling', 'sibling_yes', 'sibling_no', 'scholarship_percent', 'scholarship_fixed',
 'apply_scholarship', 'scholarship_discount', 'ভাই বা বোন ইতিমধ্যে ভর্তি?'].forEach((k) => delete data[k]);

const additions = {
  // ---- New admission form ----
  'New Admission': 'নতুন ভর্তি',
  Students: 'শিক্ষার্থী',
  'Create the student record, guardian profile, class enrolment and optional fee assignment in one step.':
    'এক ধাপে শিক্ষার্থীর রেকর্ড, অভিভাবকের প্রোফাইল, শ্রেণি নিবন্ধন ও ঐচ্ছিক ফি বরাদ্দ তৈরি করুন।',
  'All students': 'সব শিক্ষার্থী',
  'No academic session is configured. Ask an administrator to create one first.':
    'কোনো শিক্ষা সেশন নির্ধারণ করা নেই। প্রথমে একজন প্রশাসককে একটি সেশন তৈরি করতে বলুন।',
  'Student Information': 'শিক্ষার্থীর তথ্য',
  'Official details used on records and result sheets': 'রেকর্ড ও ফলাফলের শীটে ব্যবহৃত আনুষ্ঠানিক তথ্য',
  'Required fields marked *': '* চিহ্নিত ঘরগুলো আবশ্যক',
  Identity: 'পরিচয়',
  'Student name (English)': 'শিক্ষার্থীর নাম (English)',
  'Student name (Bangla)': 'শিক্ষার্থীর নাম (বাংলা)',
  'Name in Bangla': 'বাংলায় নাম',
  Gender: 'লিঙ্গ',
  Male: 'ছেলে',
  Female: 'মেয়ে',
  Other: 'অন্যান্য',
  'Date of birth': 'জন্ম তারিখ',
  'Blood group': 'রক্তের গ্রুপ',
  Religion: 'ধর্ম',
  Nationality: 'জাতীয়তা',
  'Student photo': 'শিক্ষার্থীর ছবি',
  'JPG, PNG or WebP, max 2 MB.': 'JPG, PNG অথবা WebP, সর্বোচ্চ ২ মেগাবাইট।',
  Contact: 'যোগাযোগ',
  'Student phone': 'শিক্ষার্থীর ফোন',
  'Student email': 'শিক্ষার্থীর ইমেইল',
  Address: 'ঠিকানা',
  City: 'শহর',
  'State / Division': 'জেলা / বিভাগ',
  'Postal code': 'ডাক কোড',
  'Previous school': 'পূর্ববর্তী বিদ্যালয়',
  'Medical notes': 'চিকিৎসা সংক্রান্ত টীকা',
  'Allergies, medication or accessibility needs': 'অ্যালার্জি, ওষুধ অথবা বিশেষ সহায়তার প্রয়োজন',

  // ---- Guardian + sibling ----
  'Guardian Information': 'অভিভাবকের তথ্য',
  'Primary contact for fees and communications': 'ফি ও যোগাযোগের জন্য প্রধান ব্যক্তি',
  'Brother or sister already admitted?': 'ভাই বা বোন ইতিমধ্যে ভর্তি আছে?',
  Yes: 'হ্যাঁ',
  No: 'না',
  'Select Yes and pick the enrolled sibling in the Sibling Student box. Guardian details auto-fill from the sibling; you can still change them.':
    'হ্যাঁ নির্বাচন করে Sibling Student বক্সে ভর্তি ভাই বা বোন নির্বাচন করুন। অভিভাবকের তথ্য স্বয়ংক্রিয়ভাবে পূরণ হবে; প্রয়োজনে পরিবর্তন করতে পারবেন।',
  'Sibling Student': 'ভাই/বোনের রেকর্ড',
  'Search an already enrolled brother or sister': 'ইতিমধ্যে ভর্তি ভাই বা বোন খুঁজুন',
  Enabled: 'সক্রিয়',
  'Read only': 'শুধু দেখার জন্য',
  'Search sibling student': 'ভাই/বোন খুঁজুন',
  'Type name, admission no or ID...': 'নাম, ভর্তি নম্বর বা আইডি লিখুন...',
  'Select Yes above to enable': 'সক্রিয় করতে উপরে হ্যাঁ নির্বাচন করুন',
  'Searching...': 'খোঁজা হচ্ছে...',
  'No students found.': 'কোনো শিক্ষার্থী পাওয়া যায়নি।',
  Change: 'পরিবর্তন',
  'This box stays read-only when the answer is No.': 'উত্তর না হলে এই বক্সটি শুধু দেখার জন্য থাকবে।',
  'No guardian found for this student. Fill the guardian details manually.':
    'এই শিক্ষার্থীর কোনো অভিভাবক পাওয়া যায়নি। নিচে অভিভাবকের তথ্য হাতে লিখুন।',
  'Guardian details auto-filled from the sibling. You can still change them.':
    'ভাই/বোনের রেকর্ড থেকে অভিভাবকের তথ্য স্বয়ংক্রিয়ভাবে বসানো হয়েছে। প্রয়োজনে পরিবর্তন করুন।',
  'Guardian name': 'অভিভাবকের নাম',
  Relationship: 'সম্পর্ক',
  Father: 'পিতা',
  Mother: 'মাতা',
  Guardian: 'অভিভাবক',
  'Guardian phone': 'অভিভাবকের ফোন',
  'Guardian email': 'অভিভাবকের ইমেইল',
  Occupation: 'পেশা',
  'Guardian address': 'অভিভাবকের ঠিকানা',
  'This mobile number becomes the parent portal username. A sibling with the same number shares one parent account.':
    'এই মোবাইল নম্বরই অভিভাবক পোর্টালের ইউজারনেম। একই নম্বর থাকলে ভাই-বোনগুলো একটিই প্যারেন্ট অ্যাকাউন্ট শেয়ার করবে।',

  // ---- Enrolment + fees sidebar ----
  Enrolment: 'শ্রেণি নিবন্ধন',
  'Academic placement for this admission': 'এই ভর্তির শ্রেণি ও সেশন নির্ধারণ',
  'Academic session': 'শিক্ষা সেশন',
  Class: 'শ্রেণি',
  Section: 'শাখা',
  'Roll number': 'রোল নম্বর',
  'Leave blank to generate the next roll number.': 'খালি রাখলে পরবর্তী রোল নম্বর স্বয়ংক্রিয়ভাবে তৈরি হবে।',
  'Admission date': 'ভর্তির তারিখ',
  Fees: 'ফি',
  'Optionally assign the class fee structure now': 'চাইলে এখনই শ্রেণির ফি স্ট্রাকচার বরাদ্দ করুন',
  'Fee structure': 'ফি স্ট্রাকচার',
  'Assign later': 'পরে বরাদ্দ করা হবে',
  'Monthly items generate one due row per month of the session. Optional items can be added from the collection page.':
    'মাসিক আইটেম প্রতি মাসে একটি করে বকেয়া সারি তৈরি করে। ঐচ্ছিক আইটেম ফি আদায় পাতা থেকে যোগ করা যায়।',
  'Financial records are never hard-deleted. Receipts must be reversed to preserve a complete audit trail.':
    'আর্থিক রেকর্ড কখনো স্থায়ীভাবে মুছে ফেলা হয় না। পুরো অডিট ট্রেইল রাখতে রসিদ রদ (reverse) করতে হয়।',
  'Save admission': 'ভর্তি সংরক্ষণ',
  'The student ID, portal accounts, guardian link, enrolment and fee assignment are written in one database transaction.':
    'শিক্ষার্থী আইডি, পোর্টাল অ্যাকাউন্ট, অভিভাবকের সংযোগ, নিবন্ধন ও ফি বরাদ্দ একটি ডেটাবেস ট্রানজেকশনে লেখা হয়।',
  'A permanent Student ID is generated as :format (for example 202601001). The student and guardian receive automatic portal logins; the credentials are shown once on the next screen.':
    'স্থায়ী শিক্ষার্থী আইডি :format আকারে তৈরি হয় (যেমন ২০২৬০১০০১)। শিক্ষার্থী ও অভিভাবকের পোর্টাল লগইন স্বয়ংক্রিয়ভাবে তৈরি হয়; পাসওয়ার্ড পরবর্তী পাতায় একবার দেখানো হবে।',
  'Admit student': 'ভর্তি সম্পন্ন করুন',
  Cancel: 'বাতিল',

  // ---- Scholarship (Fees & Payments) ----
  scholarship: 'স্কলারশিপ',
  Scholarship: 'স্কলারশিপ',
  'Scholarship · :fee': 'স্কলারশিপ · :fee',
  'Set scholarship': 'স্কলারশিপ নির্ধারণ',
  'Scholarship type': 'স্কলারশিপের ধরন',
  'Percentage (%)': 'শতকরা (%)',
  'Fixed amount': 'নির্দিষ্ট পরিমাণ',
  'No scholarship': 'স্কলারশিপ নেই',
  Value: 'পরিমাণ',
  'Apply scholarship': 'স্কলারশিপ প্রয়োগ করুন',
  'Current scholarship': 'বর্তমান স্কলারশিপ',
  'Enter the scholarship percentage or fixed amount you want to grant.':
    'যে শতকরা বা নির্দিষ্ট পরিমাণ ছাড় দিতে চান তা লিখুন।',
  'Scholarship applied to this fee row.': 'এই ফি সারিতে স্কলারশিপ প্রয়োগ করা হয়েছে।',
  'Scholarship removed from this fee row.': 'এই ফি সারি থেকে স্কলারশিপ সরানো হয়েছে।',
  'Scholarship is set per fee': 'প্রতিটি ফিতে আলাদাভাবে স্কলারশিপ নির্ধারণ করা হয়',
  'Scholarship is granted per fee row — use the award icon on any outstanding fee to set a percentage or fixed concession before collecting.':
    'স্কলারশিপ প্রতিটি ফি সারিতে দেওয়া হয় — আদায়ের আগে যেকোনো বকেয়া ফির graduation-cap আইকনে শতকরা বা নির্দিষ্ট পরিমাণ ছাড় নির্ধারণ করুন।',
  'Choose how the concession is calculated: a percentage of this fee, or a fixed amount. It reduces the payable balance immediately.':
    'ছাড়টি কীভাবে হিসাব হবে বেছে নিন: এই ফির শতকরা অংশ, নাকি নির্দিষ্ট পরিমাণ। এটি সঙ্গে সঙ্গে প্রদেয় বকেয়া কমিয়ে দেবে।',
  'Scholarship conceded on this receipt: :amount': 'এই রসিদে প্রদত্ত স্কলারশিপ: :amount',
  ':pct% concession': ':pct% ছাড়',
  'fixed scholarship': 'নির্দিষ্ট পরিমাণ স্কলারশিপ',
  'Fixed scholarship': 'নির্দিষ্ট পরিমাণ স্কলারশিপ',
  Waives: 'ছাড় হবে',
  '% (percent)': '% (শতকরা)',
  'Fixed ৳': 'নির্দিষ্ট ৳',
  'A scholarship percentage cannot exceed 100.': 'স্কলারশিপের শতকরা হার ১০০-এর বেশি হতে পারে না।',
  'A fixed scholarship cannot exceed the fee amount.': 'নির্দিষ্ট স্কলারশিপ ফির পরিমাণের চেয়ে বেশি হতে পারে না।',
  'Choose percentage or fixed scholarship.': 'শতকরা নাকি নির্দিষ্ট পরিমাণ স্কলারশিপ — বেছে নিন।',
  'Scholarship cannot be negative.': 'স্কলারশিপ ঋণাত্মক হতে পারে না।',
};

const before = Object.keys(data).length;
for (const [key, value] of Object.entries(additions)) {
  if (!(key in data)) data[key] = value;
}

fs.writeFileSync(path, JSON.stringify(data, null, 4) + '\n', 'utf8');
console.log(`before=${before} after=${Object.keys(data).length} added=${Object.keys(data).length - before}`);

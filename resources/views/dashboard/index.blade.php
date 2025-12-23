@extends('layouts.dashboard')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Welcome Section -->
    <div class="lg:col-span-2 bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-900 mb-2">مرحباً, {{ auth()->user()->name ?? 'Admin' }}</h2>
                <p class="text-gray-600">يمكنك البدء بإدارة المحتوى.</p>
                <p class="text-gray-600 mt-2">الأدوار -</p>
            </div>
        </div>
    </div>

    <!-- Categories Section -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">التصنيفات</h3>
            <span class="text-sm text-gray-500">0 عنصر</span>
        </div>
        <form class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">الاسم</label>
                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="أدخل اسم التصنيف">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">المعرف (slug)</label>
                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="أدخل المعرف">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">الوصف</label>
                <textarea rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="أدخل الوصف"></textarea>
            </div>
            <button type="submit" class="w-full bg-purple-600 text-white py-3 rounded-lg font-medium hover:bg-purple-700 transition-colors">
                إضافة
            </button>
        </form>
    </div>

    <!-- Skills Section -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">المهارات</h3>
            <span class="text-sm text-gray-500">0 عنصر</span>
        </div>
        <form class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">الاسم</label>
                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="أدخل اسم المهارة">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">المعرف (slug)</label>
                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="أدخل المعرف">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">رمز/إشارة الأيقونة</label>
                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="أدخل رمز الأيقونة">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">الوصف</label>
                <textarea rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="أدخل الوصف"></textarea>
            </div>
            <button type="submit" class="w-full bg-purple-600 text-white py-3 rounded-lg font-medium hover:bg-purple-700 transition-colors">
                إضافة
            </button>
        </form>
    </div>

    <!-- Projects Section -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">المشاريع</h3>
            <span class="text-sm text-gray-500">0 عنصر</span>
        </div>
        <form class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">العنوان</label>
                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="أدخل عنوان المشروع">
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" id="draft" class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                <label for="draft" class="text-sm font-medium text-gray-700">مسودة</label>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">التصنيفات</label>
                <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option>اختر التصنيفات</option>
                </select>
            </div>
            <button type="submit" class="w-full bg-purple-600 text-white py-3 rounded-lg font-medium hover:bg-purple-700 transition-colors">
                إضافة مشروع
            </button>
        </form>
    </div>

    <!-- Additional Skills Section for Projects -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">المهارات</h3>
            <span class="text-sm text-gray-500">0 عنصر</span>
        </div>
        <form class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">المعرف (slug)</label>
                <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="أدخل المعرف">
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" id="featured" class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                <label for="featured" class="text-sm font-medium text-gray-700">مميز</label>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">المهارات</label>
                <textarea rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="أدخل المهارات"></textarea>
            </div>
            <button type="submit" class="w-full bg-purple-600 text-white py-3 rounded-lg font-medium hover:bg-purple-700 transition-colors">
                إضافة مشروع
            </button>
        </form>
    </div>
</div>
@endsection

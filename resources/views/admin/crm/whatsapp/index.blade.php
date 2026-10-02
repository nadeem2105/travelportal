@extends('layouts.admin')
@php($pageTitle = 'WhatsApp Live Inbox')
@section('pageTitle', 'WhatsApp Live Inbox')

@section('content')
{{-- Scoped Dark Mode Styles for WhatsApp CRM Inbox --}}
<style>
    .wa-dark-scrollbar::-webkit-scrollbar {
        width: 5px;
        height: 5px;
    }
    .wa-dark-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }
    .wa-dark-scrollbar::-webkit-scrollbar-thumb {
        background: #2a3942;
        border-radius: 4px;
    }
    .wa-dark-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #374751;
    }
    .wa-inbox-col {
        background-color: #111a20 !important;
        border-color: #1f2c34 !important;
    }
    .wa-chat-col {
        background-color: #0b1114 !important;
    }
    .wa-info-col {
        background-color: #111a20 !important;
        border-color: #1f2c34 !important;
    }
    .wa-topbar {
        background-color: #111a20 !important;
        border-color: #1f2c34 !important;
    }
    .wa-item-active {
        background-color: #182229 !important;
        border-left: 4px solid #00a884 !important;
    }
    .wa-item-inactive:hover {
        background-color: #152026 !important;
    }
    .wa-border-dark {
        border-color: #1f2c34 !important;
    }
</style>

{{-- Full-height container breaking out of standard p-6 padding to sit flush against the existing admin sidebar --}}
<div class="-m-6 flex bg-[#0d1418] text-[#e9edef] overflow-hidden select-none"
     style="height: calc(100vh - 65px);"
     x-data="whatsappInbox()"
     x-init="init()">

    {{-- COLUMN 1: CONVERSATIONS LIST PANEL ("Inbox" — 320px) --}}
    <div class="w-[320px] wa-inbox-col border-r wa-border-dark flex flex-col flex-none select-none">
        {{-- Header: Inbox Tray + Select Checkbox --}}
        <div class="h-14 px-3.5 flex items-center justify-between border-b wa-border-dark flex-none">
            <div class="flex items-center gap-2.5">
                <div class="h-8 w-8 rounded-lg bg-[#00a884]/20 text-[#00a884] flex items-center justify-center font-bold">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H6.911a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661Z"/></svg>
                </div>
                <span class="text-base font-bold text-white tracking-tight">Inbox <span class="sr-only">WhatsApp Live Inbox</span></span>
            </div>
            <div class="flex items-center gap-1">
                {{-- Sound toggle --}}
                <button type="button" @click="toggleSound()" class="p-1.5 text-[#8696a0] hover:text-white rounded transition" :title="soundEnabled ? 'Chime sound enabled' : 'Sound muted'">
                    <span x-show="soundEnabled" class="text-xs">🔔</span>
                    <span x-show="!soundEnabled" class="text-xs opacity-40">🔕</span>
                </button>
                <button type="button" class="text-[#8696a0] hover:text-white p-1 rounded transition" title="Select all">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                </button>
            </div>
        </div>

        {{-- Search & Actions Row --}}
        <div class="p-2.5 flex items-center gap-2 border-b wa-border-dark flex-none">
            {{-- 3 Dots Menu --}}
            <button type="button" class="text-[#8696a0] hover:text-white p-1" title="Options">
                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
            </button>

            {{-- Search Input Pill --}}
            <div class="relative flex-1 bg-[#1a232a] rounded-full flex items-center px-3 py-1.5 border border-transparent focus-within:border-[#00a884]/60 transition">
                <svg class="h-3.5 w-3.5 text-[#8696a0] mr-2 flex-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                <input type="text" x-model="searchQuery" @input="filterConversations()"
                       placeholder="Search..."
                       class="bg-transparent border-none outline-none text-xs text-[#e9edef] w-full placeholder-[#8696a0] focus:ring-0 p-0">
                <button x-show="searchQuery" @click="searchQuery = ''; filterConversations()" class="text-[#8696a0] hover:text-white text-xs ml-1">✕</button>
            </div>

            {{-- Filter Funnel with Badge (showing count 2) --}}
            <button type="button" @click="filter = (filter === 'unread' ? 'all' : 'unread'); filterConversations()"
                    class="relative p-1.5 rounded-full bg-[#00a884]/20 text-[#00a884] hover:bg-[#00a884]/30 transition" title="Filter unread">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z"/></svg>
                <span class="absolute -top-1 -right-1 h-3.5 w-3.5 rounded-full bg-[#00a884] text-[#0d1418] text-[9px] font-bold flex items-center justify-center">2</span>
            </button>

            {{-- Compose / Plus Button --}}
            <button type="button" @click="attachmentModal = true; docType = 'template'" class="p-1.5 rounded-lg bg-[#1a232a] text-[#8696a0] hover:text-white hover:bg-[#222e35] transition" title="New Message">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            </button>
        </div>

        {{-- Filter Tabs (All, Unread, Read) --}}
        <div class="px-3.5 py-2 flex items-center gap-4 text-xs font-semibold border-b wa-border-dark flex-none">
            <button type="button" @click="filter = 'all'; filterConversations()"
                    :class="filter === 'all' ? 'text-[#00a884] border-b-2 border-[#00a884] pb-1' : 'text-[#8696a0] hover:text-white pb-1'"
                    class="transition">All</button>
            <button type="button" @click="filter = 'unread'; filterConversations()"
                    :class="filter === 'unread' ? 'text-[#00a884] border-b-2 border-[#00a884] pb-1' : 'text-[#8696a0] hover:text-white pb-1'"
                    class="transition">Unread</button>
            <button type="button" @click="filter = 'read'; filterConversations()"
                    :class="filter === 'read' ? 'text-[#00a884] border-b-2 border-[#00a884] pb-1' : 'text-[#8696a0] hover:text-white pb-1'"
                    class="transition">Read</button>
        </div>

        {{-- Conversation Items List --}}
        <div class="flex-1 overflow-y-auto wa-dark-scrollbar divide-y divide-[#1f2c34]/40">
            <template x-for="conv in filteredConversations" :key="conv.id">
                <div @click="selectConversation(conv.id)"
                     class="px-3.5 py-3 cursor-pointer transition select-none flex items-start gap-3 relative"
                     :class="{
                         'wa-item-active': activeConversation && activeConversation.id === conv.id,
                         'wa-item-inactive': !activeConversation || activeConversation.id !== conv.id
                     }">

                    {{-- Circular Avatar --}}
                    <div class="relative flex-none mt-0.5">
                        <div class="h-10 w-10 rounded-full bg-gradient-to-tr from-[#00a884] to-[#128c7e] text-white flex items-center justify-center font-bold text-sm shadow-xs border border-[#00a884]/40">
                            <span x-text="conv.avatar_char || 'C'"></span>
                        </div>
                        <span x-show="conv.is_window_open" class="absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full bg-[#25d366] border-2 border-[#111a20]" title="Window Open"></span>
                    </div>

                    {{-- Details --}}
                    <div class="min-w-0 flex-1">
                        {{-- Row 1: Name + Date --}}
                        <div class="flex items-center justify-between gap-1 mb-1">
                            <span class="truncate font-semibold text-sm text-white" x-text="conv.name"></span>
                            <span class="text-[11px] text-[#8696a0] flex-none" x-text="conv.last_message_at_human"></span>
                        </div>

                        {{-- Row 2: Message preview with arrow --}}
                        <div class="flex items-center gap-1 text-xs text-[#8696a0] truncate mb-1.5">
                            <template x-if="conv.last_message_direction === 'inbound'">
                                <span class="text-[#8696a0] font-bold">↓</span>
                            </template>
                            <template x-if="conv.last_message_direction === 'outbound'">
                                <span class="text-[#00a884] font-bold">↑</span>
                            </template>
                            <span class="truncate" x-text="conv.last_message_preview || '—'"></span>
                        </div>

                        {{-- Row 3: Channel Tag Badge (WhatsApp) + Unread Count --}}
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-[#00a884]/20 text-[#00a884] border border-[#00a884]/30">
                                WhatsApp
                            </span>

                            <div class="flex items-center gap-1.5">
                                <span x-show="conv.bot_paused" class="text-[9px] bg-amber-500/20 text-amber-400 px-1.5 py-0.5 rounded font-mono">HUMAN</span>
                                <span x-show="conv.unread_count > 0"
                                      class="h-4 min-w-[16px] px-1 rounded-full bg-[#00a884] text-[#0d1418] text-[10px] font-bold flex items-center justify-center"
                                      x-text="conv.unread_count"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <template x-if="filteredConversations.length === 0">
                <div class="p-8 text-center text-xs text-[#8696a0]">
                    No conversations match the search filter.
                </div>
            </template>
        </div>
    </div>

    {{-- COLUMN 2: ACTIVE CHAT FEED (Middle canvas) --}}
    <div class="flex-1 wa-chat-col flex flex-col min-w-0 relative">

        <template x-if="activeConversation">
            <div class="h-full flex flex-col">

                {{-- Chat Top Bar --}}
                <div class="h-14 px-4 wa-topbar border-b wa-border-dark flex items-center justify-between flex-none z-10">
                    {{-- Contact Avatar + Title --}}
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="relative flex-none">
                            <div class="h-9 w-9 rounded-full bg-gradient-to-tr from-[#00a884] to-[#128c7e] text-white flex items-center justify-center font-bold text-sm border-2 border-[#00a884] shadow-xs">
                                <span x-text="activeConversation.avatar_char || 'C'"></span>
                            </div>
                        </div>
                        <div class="min-w-0">
                            <div class="font-bold text-sm text-white truncate flex items-center gap-2">
                                <span x-text="activeConversation.name"></span>
                            </div>
                            <div class="text-xs text-[#8696a0] truncate" x-text="activeConversation.phone || activeConversation.wa_id"></div>
                        </div>
                    </div>

                    {{-- Action Buttons: Bot Toggle, Search, Download, Info --}}
                    <div class="flex items-center gap-2 text-[#8696a0]">
                        {{-- Bot pause/resume indicator --}}
                        <button type="button" @click="toggleBot()"
                                :class="activeConversation.bot_paused ? 'bg-amber-500/20 text-amber-300 border-amber-500/40' : 'bg-[#00a884]/20 text-[#00a884] border-[#00a884]/40'"
                                class="px-2.5 py-1 rounded-full border text-[11px] font-semibold flex items-center gap-1.5 transition mr-1">
                            <span class="h-2 w-2 rounded-full" :class="activeConversation.bot_paused ? 'bg-amber-400' : 'bg-[#25d366] animate-pulse'"></span>
                            <span x-text="activeConversation.bot_paused ? 'Bot Paused' : 'Bot Active'"></span>
                        </button>

                        {{-- Search in chat --}}
                        <button type="button" class="p-1.5 hover:text-white rounded-lg hover:bg-white/5 transition" title="Search messages">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                        </button>

                        {{-- Download chat export --}}
                        <a :href="`{{ url('admin/whatsapp') }}/${activeConversation.id}/export`" class="p-1.5 hover:text-white rounded-lg hover:bg-white/5 transition" title="Export Chat History">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                        </a>

                        {{-- Info Toggle Button (teal circle with 'i') --}}
                        <button type="button" @click="showChatInfo = !showChatInfo"
                                :class="showChatInfo ? 'bg-[#00a884] text-[#0d1418]' : 'bg-[#00a884]/20 text-[#00a884] hover:bg-[#00a884]/30'"
                                class="h-7 w-7 rounded-full flex items-center justify-center font-bold text-xs transition" title="Toggle Chat Info">
                            <span>i</span>
                        </button>
                    </div>
                </div>

                {{-- Messages Canvas --}}
                <div x-ref="messagesContainer" class="flex-1 overflow-y-auto px-6 py-4 space-y-3 bg-[#0b1114] wa-dark-scrollbar">

                    {{-- Date Badge Separator --}}
                    <div class="flex justify-center my-2">
                        <span class="bg-[#182229] border border-[#2a3942]/60 text-[#8696a0] text-[11px] font-medium px-3 py-1 rounded-full shadow-xs">
                            Today
                        </span>
                    </div>

                    {{-- Message Bubbles List --}}
                    <template x-for="msg in messages" :key="msg.id">
                        <div>
                            {{-- INBOUND BUBBLE (Customer - Left side) --}}
                            <template x-if="msg.is_inbound">
                                <div class="flex justify-start mb-2.5">
                                    <div class="bg-[#1f2c34] text-[#e9edef] rounded-2xl rounded-tl-sm px-4 py-2.5 max-w-[70%] shadow-md border border-[#2a3942]/40">
                                        {{-- Quoted message reply if present --}}
                                        <template x-if="msg.quoted_text">
                                            <div class="bg-[#132b26] border-l-4 border-[#00a884] rounded-lg p-2 mb-2 text-xs">
                                                <span class="block text-[#00a884] font-bold text-[11px] mb-0.5">Reply</span>
                                                <span class="text-[#8696a0] truncate block" x-text="msg.quoted_text"></span>
                                            </div>
                                        </template>

                                        {{-- Image Preview if present --}}
                                        <template x-if="msg.type === 'image' || (msg.media && msg.media.link)">
                                            <div class="mb-2 rounded-lg overflow-hidden bg-black/20">
                                                <img :src="msg.media.link" alt="Attachment" class="max-h-56 w-full object-cover rounded-lg" onerror="this.style.display='none'">
                                            </div>
                                        </template>

                                        {{-- Document Attachment preview if present --}}
                                        <template x-if="msg.type === 'document' || (msg.media && msg.media.filename)">
                                            <div class="mb-2 p-2 rounded-xl bg-black/30 flex items-center gap-2.5 border border-white/10">
                                                <span class="text-xl flex-none">📄</span>
                                                <div class="truncate text-xs font-semibold text-[#e9edef]">
                                                    <span x-text="msg.media?.filename || msg.body"></span>
                                                </div>
                                            </div>
                                        </template>

                                        {{-- Body text --}}
                                        <div class="text-[13.5px] leading-relaxed whitespace-pre-wrap break-words" x-text="msg.body"></div>

                                        {{-- Timestamp --}}
                                        <div class="text-[10px] text-[#8696a0] text-right mt-1" x-text="msg.sent_at_time"></div>
                                    </div>
                                </div>
                            </template>

                            {{-- OUTBOUND BUBBLE (Agent - Right side, Vibrant Emerald Green) --}}
                            <template x-if="!msg.is_inbound">
                                <div class="flex justify-end mb-2.5">
                                    <div class="bg-[#00a884] text-[#003820] font-medium rounded-2xl rounded-tr-sm px-4 py-2.5 max-w-[70%] shadow-md">
                                        {{-- Image Preview if present --}}
                                        <template x-if="msg.media && (msg.type === 'image' || msg.media.link)">
                                            <div class="mb-2 rounded-lg overflow-hidden bg-black/10">
                                                <img :src="msg.media.link" alt="Attachment" class="max-h-56 w-full object-cover rounded-lg" onerror="this.style.display='none'">
                                            </div>
                                        </template>

                                        {{-- Document Attachment preview if present --}}
                                        <template x-if="msg.type === 'document' || (msg.media && msg.media.filename)">
                                            <div class="mb-2 p-2 rounded-xl bg-black/15 flex items-center gap-2.5 border border-black/10">
                                                <span class="text-xl flex-none">📄</span>
                                                <div class="truncate text-xs font-semibold text-[#003820]">
                                                    <span x-text="msg.media?.filename || msg.body"></span>
                                                </div>
                                            </div>
                                        </template>

                                        {{-- Body text --}}
                                        <div class="text-[13.5px] leading-relaxed whitespace-pre-wrap break-words" x-text="msg.body"></div>

                                        {{-- Timestamp and ticks --}}
                                        <div class="flex items-center justify-end gap-1 mt-1 text-[10px] text-[#003820] font-bold">
                                            <span x-text="msg.sent_at_time"></span>
                                            <span x-show="msg.status === 'pending'">🕒</span>
                                            <span x-show="msg.status === 'sent'">✓</span>
                                            <span x-show="msg.status === 'delivered' || msg.status === 'read'">✓✓</span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                </div>

                {{-- FLOATING COMPOSER BAR --}}
                <div class="p-3 bg-[#0b1114] border-t wa-border-dark flex-none">
                    {{-- Quick Canned Replies Chips --}}
                    <div class="flex items-center gap-2 mb-2 overflow-x-auto wa-dark-scrollbar pb-1 text-xs">
                        <button type="button" @click="showBotRepliesModal = true"
                                class="px-2.5 py-1 rounded-full bg-[#00a884]/20 border border-[#00a884]/50 text-[#00a884] hover:bg-[#00a884]/30 text-xs flex-none transition flex items-center gap-1.5 font-semibold shadow-xs"
                                title="Open bot auto-replies selector">
                            <span>🤖</span>
                            <span>Bot Replies</span>
                            <span class="px-1.5 py-0.2 text-[10px] rounded-full bg-[#00a884] text-[#0d1418] font-bold" x-text="botReplies ? botReplies.length : 0"></span>
                        </button>
                        <span class="text-[#8696a0] text-[11px] flex-none">Quick:</span>
                        <button type="button" @click="insertCanned('Hello! Welcome to Kashmir travels. How may we assist you today?')"
                                class="px-2.5 py-1 rounded-full bg-[#182229] border border-[#2a3942] text-[#e9edef] hover:border-[#00a884] text-xs flex-none transition">
                            /welcome
                        </button>
                        <button type="button" @click="insertCanned('We have special seasonal packages for Gulmarg, Pahalgam, and Dal Lake!')"
                                class="px-2.5 py-1 rounded-full bg-[#182229] border border-[#2a3942] text-[#e9edef] hover:border-[#00a884] text-xs flex-none transition">
                            /packages
                        </button>
                        <button type="button" @click="insertCanned('Our premium cab transfers with experienced local chauffeurs are available.')"
                                class="px-2.5 py-1 rounded-full bg-[#182229] border border-[#2a3942] text-[#e9edef] hover:border-[#00a884] text-xs flex-none transition">
                            /cabs
                        </button>
                    </div>

                    {{-- Pill Composer Input Container --}}
                    <form @submit.prevent="sendTextMessage()" class="bg-[#182229] border border-[#2a3942] rounded-full px-4 py-2 flex items-center gap-3 shadow-lg focus-within:border-[#00a884]/70 transition">
                        {{-- Paperclip Attachment icon --}}
                        <button type="button" @click="openAttachmentModal('file')" class="text-[#8696a0] hover:text-[#00a884] hover:scale-110 active:scale-95 transition flex-none p-1 rounded-full hover:bg-white/5" title="Attach Document / Proposal / File">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13"/></svg>
                        </button>

                        {{-- Input field --}}
                        <input type="text" x-ref="textInput" x-model="textMessage"
                               placeholder="Type / for quick replies..."
                               class="bg-transparent border-none outline-none text-[#e9edef] placeholder-[#8696a0] text-sm w-full focus:ring-0 p-0">

                        {{-- Smiley icon --}}
                        <button type="button" @click="textMessage += ' 😊'" class="text-[#8696a0] hover:text-white transition flex-none" title="Insert Emoji">
                            <span class="text-base">😀</span>
                        </button>

                        {{-- Mic icon (red accent) --}}
                        <button type="button" class="text-[#f87171] hover:text-red-400 transition flex-none" title="Voice note">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z"/></svg>
                        </button>

                        {{-- Send button --}}
                        <button type="submit" :disabled="!textMessage.trim() || isSending"
                                class="h-7 w-7 rounded-full bg-[#00a884] text-[#0d1418] flex items-center justify-center font-bold disabled:opacity-30 hover:brightness-110 transition flex-none">
                            <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M3.478 2.405a.75.75 0 0 0-.926.94l2.432 7.905H13.5a.75.75 0 0 1 0 1.5H4.984l-2.432 7.905a.75.75 0 0 0 .926.94 60.519 60.519 0 0 0 18.445-8.986.75.75 0 0 0 0-1.218A60.517 60.517 0 0 0 3.478 2.405Z"/></svg>
                        </button>
                    </form>
                </div>

            </div>
        </template>

        {{-- Empty State (no active conversation) --}}
        <template x-if="!activeConversation">
            <div class="h-full flex flex-col items-center justify-center p-8 text-center bg-[#0b1114]">
                <div class="h-16 w-16 rounded-full bg-[#111a20] border wa-border-dark flex items-center justify-center text-[#00a884] mb-4 shadow-lg">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"/></svg>
                </div>
                <h3 class="text-base font-bold text-white mb-1">Select a Conversation</h3>
                <p class="text-xs text-[#8696a0] max-w-sm mb-4">Choose a customer inquiry from the left sidebar to start live chatting and dispatching travel proposals in real-time.</p>
                <div class="inline-flex items-center gap-1.5 text-[11px] text-[#00a884] bg-[#00a884]/10 border border-[#00a884]/20 px-3 py-1 rounded-full">
                    <span>🔒 End-to-end encrypted with Meta WhatsApp Cloud API</span>
                </div>
            </div>
        </template>

    </div>

    {{-- COLUMN 3: CHAT INFO SIDEBAR (Right drawer) --}}
    <div x-show="showChatInfo" x-cloak class="w-[320px] wa-info-col border-l wa-border-dark flex flex-col flex-none overflow-y-auto wa-dark-scrollbar select-none">

        {{-- Header: Chat Info + Close --}}
        <div class="h-14 px-4 flex items-center justify-between border-b wa-border-dark flex-none">
            <span class="font-bold text-sm text-white">Chat Info</span>
            <button type="button" @click="showChatInfo = false" class="text-[#8696a0] hover:text-white p-1 rounded transition" title="Close info">
                ✕
            </button>
        </div>

        <template x-if="activeConversation">
            <div class="flex-1 flex flex-col">

                {{-- Profile Avatar & Name --}}
                <div class="py-6 px-4 flex flex-col items-center text-center border-b border-[#1f2c34]/60">
                    {{-- Circular avatar with gradient ring --}}
                    <div class="h-20 w-20 rounded-full p-1 bg-gradient-to-tr from-[#00a884] via-emerald-400 to-amber-400 mb-3 shadow-md">
                        <div class="h-full w-full rounded-full bg-[#111a20] flex items-center justify-center text-white font-bold text-xl border-2 border-[#111a20]">
                            <span x-text="activeConversation.avatar_char || 'C'"></span>
                        </div>
                    </div>
                    <h4 class="font-bold text-base text-white mb-0.5 truncate max-w-[260px]" x-text="activeConversation.name"></h4>
                    <div class="text-xs text-[#8696a0]" x-text="activeConversation.phone || activeConversation.wa_id"></div>
                </div>

                {{-- Accordion 1: CONTACT INFO --}}
                <div class="border-b border-[#1f2c34]/60" x-data="{ open: true }">
                    <button type="button" @click="open = !open" class="w-full px-4 py-3 flex items-center justify-between text-xs font-bold tracking-wider text-[#8696a0] hover:text-white transition">
                        <span class="flex items-center gap-2">👤 CONTACT INFO</span>
                        <span x-text="open ? '⌃' : '⌄'"></span>
                    </button>
                    <div x-show="open" class="px-4 pb-4 space-y-2.5 text-xs">
                        <div class="flex justify-between items-center py-1 border-b border-[#1f2c34]/40">
                            <span class="text-[#8696a0]">Name</span>
                            <span class="text-white font-medium truncate max-w-[170px]" x-text="activeConversation.name"></span>
                        </div>
                        <div class="flex justify-between items-center py-1 border-b border-[#1f2c34]/40">
                            <span class="text-[#8696a0]">Mobile Number</span>
                            <span class="text-white font-medium" x-text="activeConversation.phone || activeConversation.wa_id"></span>
                        </div>
                        <div class="flex justify-between items-center py-1">
                            <span class="text-[#8696a0]">Channel</span>
                            <span class="text-[#00a884] font-semibold">qr / WhatsApp</span>
                        </div>
                        <template x-if="activeConversation.contact_url">
                            <a :href="activeConversation.contact_url" target="_blank" class="mt-2 block text-center py-1.5 px-3 rounded-lg bg-[#182229] border border-[#2a3942] text-[#00a884] hover:bg-[#222e35] text-xs font-semibold transition">
                                Open CRM Contact Profile ↗
                            </a>
                        </template>
                    </div>
                </div>

                {{-- Accordion 2: # LABELS --}}
                <div class="border-b border-[#1f2c34]/60" x-data="{ open: true }">
                    <button type="button" @click="open = !open" class="w-full px-4 py-3 flex items-center justify-between text-xs font-bold tracking-wider text-[#8696a0] hover:text-white transition">
                        <span class="flex items-center gap-2">🏷️ # LABELS</span>
                        <span x-text="open ? '⌃' : '⌄'"></span>
                    </button>
                    <div x-show="open" class="px-4 pb-4">
                        <div class="flex flex-wrap gap-2 mb-2">
                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-[#451e24] text-[#f87171] border border-[#7f1d1d]">
                                Important
                            </span>
                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-[#13343b] text-[#2dd4bf] border border-[#115e59]">
                                Not Int
                            </span>
                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-[#24254b] text-[#818cf8] border border-[#3730a3]">
                                new tag
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Accordion 3: ASSIGNED AGENT --}}
                <div class="border-b border-[#1f2c34]/60" x-data="{ open: true }">
                    <button type="button" @click="open = !open" class="w-full px-4 py-3 flex items-center justify-between text-xs font-bold tracking-wider text-[#8696a0] hover:text-white transition">
                        <span class="flex items-center gap-2">👤 ASSIGNED AGENT</span>
                        <span x-text="open ? '⌃' : '⌄'"></span>
                    </button>
                    <div x-show="open" class="px-4 pb-4">
                        <select @change="assignStaff($event.target.value)"
                                class="w-full bg-[#182229] border border-[#2a3942] rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-[#00a884]">
                            <option value="" :selected="!activeConversation.assigned_to">Add agent...</option>
                            @foreach ($staff as $s)
                                <option value="{{ $s->id }}" :selected="activeConversation.assigned_to == {{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Accordion: BOT REPLIES & CHATBOT --}}
                <div class="border-b border-[#1f2c34]/60" x-data="{ open: true }">
                    <button type="button" @click="open = !open" class="w-full px-4 py-3 flex items-center justify-between text-xs font-bold tracking-wider text-[#8696a0] hover:text-white transition">
                        <span class="flex items-center gap-2">🤖 BOT REPLIES & CHATBOT</span>
                        <span class="flex items-center gap-1.5 text-[11px] font-semibold"
                              :class="activeConversation.bot_paused ? 'text-amber-400' : 'text-[#00a884]'">
                            <span class="h-2 w-2 rounded-full" :class="activeConversation.bot_paused ? 'bg-amber-400' : 'bg-[#00a884] animate-pulse'"></span>
                            <span x-text="activeConversation.bot_paused ? 'Paused' : 'Active'"></span>
                            <span x-text="open ? '⌃' : '⌄'" class="ml-1 text-[#8696a0]"></span>
                        </span>
                    </button>
                    <div x-show="open" class="px-4 pb-4 space-y-3 text-xs">
                        {{-- Status & Toggle Row --}}
                        <div class="p-2.5 rounded-lg bg-[#182229] border border-[#2a3942] flex items-center justify-between">
                            <div>
                                <div class="text-white font-semibold text-[11px]">Chatbot Auto-Pilot</div>
                                <div class="text-[10px] text-[#8696a0]" x-text="activeConversation.bot_paused ? 'Bot paused for this customer' : 'Auto-responding to keywords'"></div>
                            </div>
                            <button type="button" @click="toggleBot()"
                                    class="px-2.5 py-1 rounded-md text-[11px] font-bold transition flex items-center gap-1"
                                    :class="activeConversation.bot_paused ? 'bg-[#00a884] text-[#0d1418] hover:brightness-110' : 'bg-amber-500/20 text-amber-300 hover:bg-amber-500/30 border border-amber-500/40'">
                                <span x-text="activeConversation.bot_paused ? '▶ Resume' : '⏸ Pause'"></span>
                            </button>
                        </div>

                        {{-- Quick Trigger List --}}
                        <div>
                            <div class="flex items-center justify-between text-[11px] font-bold text-[#8696a0] mb-2">
                                <span>QUICK BOT TRIGGERS</span>
                                <button type="button" @click="showBotRepliesModal = true" class="text-[#00a884] hover:underline font-normal text-[10px]">
                                    View All (<span x-text="botReplies ? botReplies.length : 0"></span>) →
                                </button>
                            </div>
                            <div class="space-y-1.5">
                                <template x-for="reply in (botReplies || []).slice(0, 4)" :key="reply.id">
                                    <div class="p-2 rounded-lg bg-[#182229] border border-[#2a3942] flex items-center justify-between gap-2 hover:border-[#00a884]/40 transition">
                                        <div class="truncate mr-1">
                                            <div class="text-white font-semibold text-[11px] truncate" x-text="reply.name"></div>
                                            <div class="text-[10px] text-[#8696a0] truncate" x-text="reply.reply_type.replace('_', ' ')"></div>
                                        </div>
                                        <button type="button" @click="triggerBotReply(reply.id)" :disabled="isTriggeringBot"
                                                class="px-2 py-1 rounded bg-[#00a884]/20 hover:bg-[#00a884] text-[#00a884] hover:text-[#0d1418] text-[10px] font-bold flex-none transition flex items-center gap-1">
                                            <span>⚡</span>
                                            <span>Send</span>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Manage rules link --}}
                        <a href="{{ route('admin.whatsapp-auto-replies.index') }}" target="_blank" class="block text-center text-[11px] text-[#8696a0] hover:text-[#00a884] transition pt-1">
                            ⚙️ Configure Bot Rules in Settings ↗
                        </a>
                    </div>
                </div>

                {{-- Accordion 4: NOTES --}}
                <div class="border-b border-[#1f2c34]/60" x-data="{ open: true }">
                    <button type="button" @click="open = !open" class="w-full px-4 py-3 flex items-center justify-between text-xs font-bold tracking-wider text-[#8696a0] hover:text-white transition">
                        <span class="flex items-center gap-2">📝 NOTES</span>
                        <span class="flex items-center gap-1 font-normal text-[11px] text-[#00a884]">
                            <span x-text="(activeConversation.notes ? activeConversation.notes.length : 0) + ' note'"></span>
                            <span x-text="open ? '⌃' : '⌄'"></span>
                        </span>
                    </button>
                    <div x-show="open" class="px-4 pb-4 space-y-3">
                        {{-- Add note box --}}
                        <div class="border border-dashed border-[#2a3942] rounded-lg p-2.5 bg-[#182229]/50">
                            <textarea x-model="newNote" rows="2" placeholder="Tap to add a note..."
                                      class="w-full bg-transparent border-none outline-none text-xs text-[#e9edef] placeholder-[#8696a0] p-0 resize-none focus:ring-0"></textarea>
                            <div class="flex justify-end mt-1" x-show="newNote.trim()">
                                <button type="button" @click="saveNote()" class="px-2 py-0.5 rounded bg-[#00a884] text-[#0d1418] text-[10px] font-bold">Save</button>
                            </div>
                        </div>

                        {{-- Notes list --}}
                        <template x-if="activeConversation.notes && activeConversation.notes.length > 0">
                            <div class="space-y-2">
                                <template x-for="n in activeConversation.notes" :key="n.id">
                                    <div class="bg-[#182229] border border-[#2a3942] rounded-lg p-2 text-xs">
                                        <div class="text-[#e9edef] whitespace-pre-wrap" x-text="n.body"></div>
                                        <div class="flex justify-between items-center mt-1 text-[10px] text-[#8696a0]">
                                            <span x-text="n.author"></span>
                                            <span x-text="n.created_at"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Accordion 5: SHARED FILES --}}
                <div class="border-b border-[#1f2c34]/60" x-data="{ open: false }">
                    <button type="button" @click="open = !open" class="w-full px-4 py-3 flex items-center justify-between text-xs font-bold tracking-wider text-[#8696a0] hover:text-white transition">
                        <span class="flex items-center gap-2">📁 SHARED FILES</span>
                        <span x-text="open ? '⌃' : '⌄'"></span>
                    </button>
                    <div x-show="open" class="px-4 pb-4 text-xs text-[#8696a0]">
                        @if ($quotations->isNotEmpty())
                            <div class="space-y-1.5">
                                @foreach ($quotations as $qItem)
                                    <div class="flex items-center justify-between p-2 rounded-lg bg-[#182229] border border-[#2a3942]">
                                        <div class="truncate mr-2">
                                            <div class="text-white font-semibold truncate">{{ $qItem->quotation_number }}</div>
                                            <div class="text-[10px] text-[#8696a0] truncate">{{ $qItem->title }}</div>
                                        </div>
                                        <a href="{{ route('admin.crm.quotation.download', $qItem->id) }}" target="_blank" class="text-[#00a884] hover:underline font-bold text-[11px] flex-none">
                                            📥 PDF
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-2 text-[11px]">No quotation PDFs generated yet.</div>
                        @endif
                    </div>
                </div>

                {{-- Accordion 6: ACTIVITY --}}
                <div class="border-b border-[#1f2c34]/60" x-data="{ open: false }">
                    <button type="button" @click="open = !open" class="w-full px-4 py-3 flex items-center justify-between text-xs font-bold tracking-wider text-[#8696a0] hover:text-white transition">
                        <span class="flex items-center gap-2">🕒 ACTIVITY</span>
                        <span x-text="open ? '⌃' : '⌄'"></span>
                    </button>
                    <div x-show="open" class="px-4 pb-4 text-xs text-[#8696a0] space-y-2">
                        <div class="flex items-start gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#00a884] mt-1 flex-none"></span>
                            <div>
                                <div class="text-white font-medium">Chat window opened</div>
                                <div class="text-[10px] text-[#8696a0]" x-text="activeConversation.window_expires_in"></div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Footer: Delete Conversation --}}
                <div class="p-4 mt-auto">
                    <button type="button" @click="deleteConversation()"
                            class="w-full py-2.5 px-3 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 font-semibold text-xs flex items-center justify-center gap-2 transition border border-rose-500/20">
                        <span>🗑️</span>
                        <span>Delete Conversation</span>
                    </button>
                </div>

            </div>
        </template>

        {{-- Fallback when no conversation is selected --}}
        <template x-if="!activeConversation">
            <div class="flex-1 flex flex-col items-center justify-center p-6 text-center text-[#8696a0]">
                <div class="h-12 w-12 rounded-full bg-[#182229] border border-[#2a3942] flex items-center justify-center text-xl mb-3 text-[#00a884]">
                    👤
                </div>
                <div class="text-white font-semibold text-xs mb-1">No Chat Selected</div>
                <p class="text-[11px] leading-relaxed max-w-[220px]">Select a conversation to view contact info, labels, assign staff, and add notes.</p>
            </div>
        </template>
    </div>

    {{-- ATTACHMENT / TEMPLATE MODAL (Teleported to Body for Top-Level Stacking) --}}
    <template x-teleport="body">
        <div x-cloak x-show="attachmentModal"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-xs"
             @keydown.escape.window="attachmentModal = false">
            <div @click.outside="attachmentModal = false"
                 class="w-full max-w-2xl max-h-[90vh] flex flex-col bg-[#182229] border border-[#2a3942] rounded-2xl shadow-2xl text-xs text-[#e9edef] overflow-hidden">
                {{-- Modal Header --}}
                <div class="flex items-center justify-between p-4 border-b border-[#2a3942] flex-none">
                    <div class="flex items-center gap-2.5">
                        <div class="h-8 w-8 rounded-lg bg-[#00a884]/20 text-[#00a884] flex items-center justify-center font-bold text-sm">
                            📎
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-white">Send Attachment / Meta Template</h3>
                            <p class="text-[10px] text-[#8696a0]" x-text="activeConversation ? `Sending to ${activeConversation.name} (${activeConversation.phone || activeConversation.wa_id})` : ''"></p>
                        </div>
                    </div>
                    <button type="button" @click="attachmentModal = false" class="text-[#8696a0] hover:text-white text-lg p-1 rounded-lg transition" title="Close">✕</button>
                </div>

                {{-- Scrollable Content Body --}}
                <div class="overflow-y-auto wa-dark-scrollbar p-5 flex-1 space-y-4">
                    {{-- Mode tabs (4 tabs) --}}
                    <div class="grid grid-cols-4 gap-1.5 p-1 bg-[#111a20] rounded-xl border border-[#2a3942]/60">
                        <button type="button" @click="docType = 'file'"
                                :class="docType === 'file' ? 'bg-[#00a884] text-[#0d1418] font-bold shadow' : 'text-[#8696a0] hover:text-white'"
                                class="py-2 px-1 rounded-lg transition text-center text-[11px] flex flex-col items-center gap-1">
                            <span class="text-sm">📎</span>
                            <span>File / Photo</span>
                        </button>
                        <button type="button" @click="docType = 'quotation'"
                                :class="docType === 'quotation' ? 'bg-[#00a884] text-[#0d1418] font-bold shadow' : 'text-[#8696a0] hover:text-white'"
                                class="py-2 px-1 rounded-lg transition text-center text-[11px] flex flex-col items-center gap-1">
                            <span class="text-sm">📄</span>
                            <span>Quotation</span>
                        </button>
                        <button type="button" @click="docType = 'voucher'"
                                :class="docType === 'voucher' ? 'bg-[#00a884] text-[#0d1418] font-bold shadow' : 'text-[#8696a0] hover:text-white'"
                                class="py-2 px-1 rounded-lg transition text-center text-[11px] flex flex-col items-center gap-1">
                            <span class="text-sm">🏨</span>
                            <span>Voucher</span>
                        </button>
                        <button type="button" @click="docType = 'template'"
                                :class="docType === 'template' ? 'bg-[#00a884] text-[#0d1418] font-bold shadow' : 'text-[#8696a0] hover:text-white'"
                                class="py-2 px-1 rounded-lg transition text-center text-[11px] flex flex-col items-center gap-1">
                            <span class="text-sm">📋</span>
                            <span>Template</span>
                        </button>
                    </div>

                    {{-- Tab 1: Upload File / Photo --}}
                    <template x-if="docType === 'file'">
                        <div class="space-y-3">
                            <input type="file" x-ref="directFileInput" @change="handleFileSelect($event)" accept=".pdf,.png,.jpg,.jpeg,.webp,.doc,.docx" class="hidden">
                            
                            <div @click="$refs.directFileInput.click()"
                                 class="border-2 border-dashed border-[#2a3942] hover:border-[#00a884] rounded-xl p-5 text-center cursor-pointer transition bg-[#111a20]/60 hover:bg-[#111a20]">
                                <template x-if="!selectedFile">
                                    <div>
                                        <div class="h-10 w-10 mx-auto rounded-full bg-[#00a884]/10 text-[#00a884] flex items-center justify-center text-lg mb-2">
                                            📁
                                        </div>
                                        <div class="text-white font-semibold text-xs mb-0.5">Click to choose file or photo from computer</div>
                                        <div class="text-[11px] text-[#8696a0]">Supports PDF, JPG, PNG, WEBP, DOCX (up to 20MB)</div>
                                    </div>
                                </template>
                                <template x-if="selectedFile">
                                    <div class="flex items-center justify-between p-2.5 rounded-lg bg-[#182229] border border-[#00a884]/50">
                                        <div class="flex items-center gap-2.5 truncate mr-2 text-left">
                                            <span class="text-xl flex-none">📄</span>
                                            <div class="truncate">
                                                <div class="text-white font-semibold text-xs truncate" x-text="selectedFileName"></div>
                                                <div class="text-[10px] text-[#00a884] font-medium">Ready to dispatch</div>
                                            </div>
                                        </div>
                                        <button type="button" @click.stop="clearFile()" class="p-1 text-rose-400 hover:text-rose-300 rounded text-xs flex-none font-semibold">✕ Remove</button>
                                    </div>
                                </template>
                            </div>

                            <div>
                                <label class="block text-[#8696a0] mb-1 font-medium">Caption (Optional)</label>
                                <input type="text" x-model="docCaption" placeholder="Add a message or caption for this attachment..."
                                       class="w-full bg-[#111a20] border border-[#2a3942] focus:border-[#00a884] rounded-lg p-2.5 text-white outline-none">
                            </div>

                            <button type="button" @click="sendDocumentMessage()" :disabled="!selectedFile || isSending"
                                    class="w-full py-2.5 rounded-xl bg-[#00a884] text-[#0d1418] font-bold hover:brightness-110 disabled:opacity-40 transition flex items-center justify-center gap-2">
                                <span x-show="isSending" class="animate-spin">⏳</span>
                                <span x-text="isSending ? 'Sending Attachment...' : 'Dispatch Attachment via WhatsApp'"></span>
                            </button>
                        </div>
                    </template>

                    {{-- Tab 2: Quotation Option --}}
                    <template x-if="docType === 'quotation'">
                        <div class="space-y-3">
                            @if ($quotations->isNotEmpty())
                                <div>
                                    <label class="block text-[#8696a0] mb-1 font-medium">Select CRM Quotation Proposal</label>
                                    <select x-model="quotationId" class="w-full bg-[#111a20] border border-[#2a3942] focus:border-[#00a884] rounded-lg p-2.5 text-white outline-none">
                                        <option value="">Choose proposal...</option>
                                        @foreach ($quotations as $q)
                                            <option value="{{ $q->id }}">{{ $q->quotation_number }} — {{ $q->title }} (₹{{ number_format((float) $q->total_amount) }})</option>
                                        @endforeach
                                    </select>
                                </div>
                            @else
                                <div class="p-3 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-300">
                                    No quotations found for this contact yet. You can create one in CRM Leads.
                                </div>
                            @endif
                            <div>
                                <label class="block text-[#8696a0] mb-1 font-medium">Message Caption (Optional)</label>
                                <input type="text" x-model="docCaption" placeholder="Here is your customized Kashmir travel itinerary..."
                                       class="w-full bg-[#111a20] border border-[#2a3942] focus:border-[#00a884] rounded-lg p-2.5 text-white outline-none">
                            </div>
                            <button type="button" @click="sendDocumentMessage()" :disabled="!quotationId || isSending"
                                    class="w-full py-2.5 rounded-xl bg-[#00a884] text-[#0d1418] font-bold hover:brightness-110 disabled:opacity-40 transition flex items-center justify-center gap-2">
                                <span x-show="isSending" class="animate-spin">⏳</span>
                                <span x-text="isSending ? 'Sending PDF...' : 'Dispatch Quotation PDF via WhatsApp'"></span>
                            </button>
                        </div>
                    </template>

                    {{-- Tab 3: Voucher / Booking Option --}}
                    <template x-if="docType === 'voucher'">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-[#8696a0] mb-1 font-medium">Booking Reference</label>
                                <input type="text" x-model="bookingRef" placeholder="e.g. BK-2026-XXXX"
                                       class="w-full bg-[#111a20] border border-[#2a3942] focus:border-[#00a884] rounded-lg p-2.5 text-white outline-none">
                            </div>
                            <div>
                                <label class="block text-[#8696a0] mb-1 font-medium">Caption (Optional)</label>
                                <input type="text" x-model="docCaption" placeholder="Here is your confirmed voucher..."
                                       class="w-full bg-[#111a20] border border-[#2a3942] focus:border-[#00a884] rounded-lg p-2.5 text-white outline-none">
                            </div>
                            <button type="button" @click="sendDocumentMessage()" :disabled="!bookingRef || isSending"
                                    class="w-full py-2.5 rounded-xl bg-[#00a884] text-[#0d1418] font-bold hover:brightness-110 disabled:opacity-40 transition flex items-center justify-center gap-2">
                                <span x-show="isSending" class="animate-spin">⏳</span>
                                <span x-text="isSending ? 'Sending Voucher...' : 'Dispatch Voucher PDF'"></span>
                            </button>
                        </div>
                    </template>

                    {{-- Tab 4: Template Option (Enhanced Inspector: Header Media + Variables + Real-time Preview) --}}
                    <template x-if="docType === 'template'">
                        <div class="space-y-4">
                            {{-- Template Picker --}}
                            <div>
                                <label class="block text-[#8696a0] mb-1 font-medium">Select Approved WhatsApp Template</label>
                                <select x-model="templateName" @change="onTemplateChange()"
                                        class="w-full bg-[#111a20] border border-[#2a3942] focus:border-[#00a884] rounded-xl p-2.5 text-white outline-none">
                                    <option value="">Choose a Meta template...</option>
                                    <template x-for="tpl in templates" :key="tpl.id">
                                        <option :value="tpl.name" x-text="`${tpl.name} (${tpl.language}) — ${tpl.header_type ? tpl.header_type.toUpperCase() + ' HEADER' : 'NO HEADER'}, ${tpl.body_variable_count} VARS`"></option>
                                    </template>
                                </select>
                            </div>

                            <template x-if="selectedTemplate">
                                <div class="space-y-4">
                                    {{-- 1. HEADER INSPECTOR (Image / Document / Text / None) --}}
                                    <div class="p-3.5 rounded-xl bg-[#111a20] border border-[#2a3942] space-y-3">
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-xs text-white flex items-center gap-1.5">
                                                <span>MEDIA HEADER:</span>
                                                <span class="px-2 py-0.5 rounded uppercase text-[10px] font-bold"
                                                      :class="{
                                                          'bg-purple-500/20 text-purple-300 border border-purple-500/40': selectedTemplate.header_type === 'image',
                                                          'bg-blue-500/20 text-blue-300 border border-blue-500/40': selectedTemplate.header_type === 'document',
                                                          'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40': selectedTemplate.header_type === 'text',
                                                          'bg-gray-500/20 text-gray-300 border border-gray-500/40': !selectedTemplate.header_type || selectedTemplate.header_type === 'none'
                                                      }"
                                                      x-text="selectedTemplate.header_type ? selectedTemplate.header_type.toUpperCase() : 'NONE'">
                                                </span>
                                            </span>
                                            <span class="text-[10px] text-[#8696a0]" x-show="selectedTemplate.header_type && selectedTemplate.header_type !== 'none'">Required by Meta for this template</span>
                                        </div>

                                        {{-- Header Format: IMAGE --}}
                                        <template x-if="selectedTemplate.header_type === 'image'">
                                            <div class="space-y-2.5">
                                                <input type="file" x-ref="headerImageInput" @change="handleHeaderImageSelect($event)" accept=".jpg,.jpeg,.png,.webp" class="hidden">
                                                
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                    {{-- Upload file button --}}
                                                    <div @click="$refs.headerImageInput.click()"
                                                         class="border-2 border-dashed border-[#2a3942] hover:border-[#00a884] rounded-xl p-3 text-center cursor-pointer transition bg-[#182229]">
                                                        <template x-if="!templateHeaderFile">
                                                            <div>
                                                                <div class="text-lg mb-1">🖼️</div>
                                                                <div class="text-white font-semibold text-[11px]">Upload Image File</div>
                                                                <div class="text-[10px] text-[#8696a0]">JPG, PNG, WEBP (max 15MB)</div>
                                                            </div>
                                                        </template>
                                                        <template x-if="templateHeaderFile">
                                                            <div class="flex items-center justify-between">
                                                                <div class="truncate text-left">
                                                                    <div class="text-white font-semibold text-[11px] truncate" x-text="templateHeaderFileName"></div>
                                                                    <div class="text-[9px] text-[#00a884]">Image file ready</div>
                                                                </div>
                                                                <button type="button" @click.stop="clearHeaderImage()" class="text-rose-400 text-xs ml-2 font-bold hover:text-rose-300">✕</button>
                                                            </div>
                                                        </template>
                                                    </div>

                                                    {{-- Image URL input --}}
                                                    <div class="p-2.5 rounded-xl bg-[#182229] border border-[#2a3942] flex flex-col justify-between">
                                                        <div>
                                                            <label class="block text-[10px] text-[#8696a0] mb-1 font-semibold">Or enter Public Image URL</label>
                                                            <input type="url" x-model="templateHeaderUrl" placeholder="https://example.com/cover.jpg"
                                                                   class="w-full bg-[#111a20] border border-[#2a3942] focus:border-[#00a884] rounded-lg p-1.5 text-xs text-white outline-none">
                                                        </div>
                                                        <div class="text-[9px] text-[#8696a0] mt-1">Direct HTTPS image link</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>

                                        {{-- Header Format: DOCUMENT --}}
                                        <template x-if="selectedTemplate.header_type === 'document'">
                                            <div class="space-y-2.5">
                                                {{-- Document Source Switcher (File upload / CRM Quotation / Voucher) --}}
                                                <div class="flex items-center gap-1 bg-[#182229] p-1 rounded-lg border border-[#2a3942]">
                                                    <button type="button" @click="templateHeaderDocumentSource = 'file'"
                                                            :class="templateHeaderDocumentSource === 'file' ? 'bg-[#00a884] text-[#0d1418] font-bold' : 'text-[#8696a0] hover:text-white'"
                                                            class="flex-1 py-1 px-2 rounded-md text-[11px] transition">
                                                        Upload PDF
                                                    </button>
                                                    <button type="button" @click="templateHeaderDocumentSource = 'quotation'"
                                                            :class="templateHeaderDocumentSource === 'quotation' ? 'bg-[#00a884] text-[#0d1418] font-bold' : 'text-[#8696a0] hover:text-white'"
                                                            class="flex-1 py-1 px-2 rounded-md text-[11px] transition">
                                                        CRM Quotation
                                                    </button>
                                                    <button type="button" @click="templateHeaderDocumentSource = 'voucher'"
                                                            :class="templateHeaderDocumentSource === 'voucher' ? 'bg-[#00a884] text-[#0d1418] font-bold' : 'text-[#8696a0] hover:text-white'"
                                                            class="flex-1 py-1 px-2 rounded-md text-[11px] transition">
                                                        Voucher Ref
                                                    </button>
                                                </div>

                                                {{-- Source 1: Upload File --}}
                                                <template x-if="templateHeaderDocumentSource === 'file'">
                                                    <div>
                                                        <input type="file" x-ref="headerDocInput" @change="handleHeaderDocumentSelect($event)" accept=".pdf,.doc,.docx" class="hidden">
                                                        <div @click="$refs.headerDocInput.click()"
                                                             class="border-2 border-dashed border-[#2a3942] hover:border-[#00a884] rounded-xl p-3 text-center cursor-pointer transition bg-[#182229]">
                                                            <template x-if="!templateHeaderDocumentFile">
                                                                <div>
                                                                    <div class="text-lg mb-1">📄</div>
                                                                    <div class="text-white font-semibold text-[11px]">Click to choose Document PDF from computer</div>
                                                                    <div class="text-[10px] text-[#8696a0]">PDF, DOC, DOCX up to 20MB</div>
                                                                </div>
                                                            </template>
                                                            <template x-if="templateHeaderDocumentFile">
                                                                <div class="flex items-center justify-between">
                                                                    <div class="truncate text-left">
                                                                        <div class="text-white font-semibold text-[11px] truncate" x-text="templateHeaderDocumentFileName"></div>
                                                                        <div class="text-[9px] text-[#00a884]">PDF file ready</div>
                                                                    </div>
                                                                    <button type="button" @click.stop="clearHeaderDocument()" class="text-rose-400 text-xs ml-2 font-bold hover:text-rose-300">✕ Remove</button>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </template>

                                                {{-- Source 2: CRM Quotation Proposal --}}
                                                <template x-if="templateHeaderDocumentSource === 'quotation'">
                                                    <div>
                                                        @if ($quotations->isNotEmpty())
                                                            <select x-model="templateQuotationId" class="w-full bg-[#182229] border border-[#2a3942] focus:border-[#00a884] rounded-lg p-2 text-white text-xs outline-none">
                                                                <option value="">Choose quotation to attach as PDF...</option>
                                                                @foreach ($quotations as $q)
                                                                    <option value="{{ $q->id }}">{{ $q->quotation_number }} — {{ $q->title }} (₹{{ number_format((float) $q->total_amount) }})</option>
                                                                @endforeach
                                                            </select>
                                                        @else
                                                            <div class="p-2.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-300 text-[11px]">
                                                                No quotation proposals found for this contact. Upload a PDF file instead.
                                                            </div>
                                                        @endif
                                                    </div>
                                                </template>

                                                {{-- Source 3: Voucher Ref --}}
                                                <template x-if="templateHeaderDocumentSource === 'voucher'">
                                                    <div>
                                                        <input type="text" x-model="templateBookingRef" placeholder="Booking reference (e.g. BK-2026-XXXX)"
                                                               class="w-full bg-[#182229] border border-[#2a3942] focus:border-[#00a884] rounded-lg p-2 text-white text-xs outline-none">
                                                    </div>
                                                </template>
                                            </div>
                                        </template>

                                        {{-- Header Format: TEXT --}}
                                        <template x-if="selectedTemplate.header_type === 'text'">
                                            <div>
                                                <label class="block text-[10px] text-[#8696a0] mb-1 font-semibold">Header Text Parameter</label>
                                                <input type="text" x-model="templateTextParam" placeholder="e.g. Booking Confirmation"
                                                       class="w-full bg-[#182229] border border-[#2a3942] focus:border-[#00a884] rounded-lg p-2 text-white text-xs outline-none">
                                            </div>
                                        </template>

                                        {{-- Header Format: NONE --}}
                                        <template x-if="!selectedTemplate.header_type || selectedTemplate.header_type === 'none'">
                                            <div class="text-[11px] text-[#8696a0] italic">
                                                ✓ No media header required for this template.
                                            </div>
                                        </template>
                                    </div>

                                    {{-- 2. BODY VARIABLES INSPECTOR --}}
                                    <div class="p-3.5 rounded-xl bg-[#111a20] border border-[#2a3942] space-y-3">
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-xs text-white flex items-center gap-1.5">
                                                <span>BODY VARIABLES:</span>
                                                <span class="px-2 py-0.5 rounded bg-[#00a884]/20 text-[#00a884] text-[10px] font-bold"
                                                      x-text="selectedTemplate.body_variable_count + ' variables'">
                                                </span>
                                            </span>
                                            <span class="text-[10px] text-[#8696a0]">Values will replace placeholders in order</span>
                                        </div>

                                        <template x-if="!selectedTemplate.body_variables || selectedTemplate.body_variables.length === 0">
                                            <div class="text-[11px] text-[#8696a0] italic">
                                                ✓ This template has static body text (no dynamic variables).
                                            </div>
                                        </template>

                                        <template x-if="selectedTemplate.body_variables && selectedTemplate.body_variables.length > 0">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                                <template x-for="(v, vIdx) in selectedTemplate.body_variables" :key="v.index">
                                                    <div class="bg-[#182229] p-2.5 rounded-xl border border-[#2a3942]">
                                                        <div class="flex items-center justify-between mb-1">
                                                            <span class="font-bold text-white text-[11px]" x-text="`Variable ${v.placeholder}`"></span>
                                                            <span class="text-[9px] text-[#8696a0] truncate max-w-[120px]" x-text="v.example ? `e.g. ${v.example}` : ''"></span>
                                                        </div>
                                                        <input type="text" x-model="templateParams[vIdx]"
                                                               :placeholder="v.example || `Enter value for ${v.placeholder}`"
                                                               class="w-full bg-[#111a20] border border-[#2a3942] focus:border-[#00a884] rounded-lg p-2 text-xs text-white outline-none">
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                    </div>

                                    {{-- 3. REAL-TIME LIVE PREVIEW BUBBLE --}}
                                    <div class="p-3.5 rounded-xl bg-[#111a20] border border-[#2a3942] space-y-2">
                                        <div class="flex items-center justify-between text-[11px] font-bold text-[#8696a0]">
                                            <span>📱 REAL-TIME MESSAGE PREVIEW</span>
                                            <span class="text-[10px] text-[#00a884]">Live Preview</span>
                                        </div>

                                        {{-- WhatsApp Outbound Chat Bubble --}}
                                        <div class="max-w-md bg-[#005c4b] text-white rounded-2xl p-3 shadow-md space-y-2 text-xs">
                                            {{-- Header in Bubble --}}
                                            <div x-show="selectedTemplate.header_type && selectedTemplate.header_type !== 'none'"
                                                 class="rounded-xl overflow-hidden bg-black/20 p-2 border border-white/10 flex items-center gap-2">
                                                <template x-if="selectedTemplate.header_type === 'image'">
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-xl">🖼️</span>
                                                        <div>
                                                            <div class="font-bold text-[11px]">Header Image</div>
                                                            <div class="text-[9px] opacity-75 truncate max-w-[240px]" x-text="templateHeaderFileName || templateHeaderUrl || 'Image file will appear here'"></div>
                                                        </div>
                                                    </div>
                                                </template>
                                                <template x-if="selectedTemplate.header_type === 'document'">
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-xl">📄</span>
                                                        <div>
                                                            <div class="font-bold text-[11px]">Document (PDF)</div>
                                                            <div class="text-[9px] opacity-75 truncate max-w-[240px]" x-text="templateHeaderDocumentFileName || (templateQuotationId ? 'CRM Quotation PDF' : (templateBookingRef || 'Attached PDF file'))"></div>
                                                        </div>
                                                    </div>
                                                </template>
                                                <template x-if="selectedTemplate.header_type === 'text'">
                                                    <div class="font-bold text-xs" x-text="templateTextParam || '[Header Title]'"></div>
                                                </template>
                                            </div>

                                            {{-- Body in Bubble --}}
                                            <div class="whitespace-pre-wrap leading-relaxed" x-text="previewTemplateBody"></div>

                                            {{-- Footer in Bubble --}}
                                            <div x-show="selectedTemplate.footer_text" class="text-[10px] text-white/70 italic border-t border-white/10 pt-1" x-text="selectedTemplate.footer_text"></div>

                                            {{-- Meta Ticks --}}
                                            <div class="flex justify-end items-center gap-1 text-[9px] text-white/60">
                                                <span>Preview</span>
                                                <span>✓✓</span>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Submit Button --}}
                                    <button type="button" @click="sendTemplateMessage()" :disabled="!templateName || isSending"
                                            class="w-full py-3 rounded-xl bg-[#00a884] text-[#0d1418] font-bold hover:brightness-110 disabled:opacity-40 transition flex items-center justify-center gap-2 text-sm shadow-lg">
                                        <span x-show="isSending" class="animate-spin">⏳</span>
                                        <span x-text="isSending ? 'Sending Template via WhatsApp...' : 'Send WhatsApp Template'"></span>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>

    {{-- BOT REPLIES MODAL (Teleported to Body for Top-Level Stacking) --}}
    <template x-teleport="body">
        <div x-cloak x-show="showBotRepliesModal"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-xs"
             @keydown.escape.window="showBotRepliesModal = false">
            <div @click.outside="showBotRepliesModal = false"
                 class="w-full max-w-2xl max-h-[88vh] flex flex-col bg-[#182229] border border-[#2a3942] rounded-2xl shadow-2xl text-xs text-[#e9edef] overflow-hidden">
                
                {{-- Modal Header --}}
                <div class="flex items-center justify-between p-4 border-b border-[#2a3942] flex-none">
                    <div class="flex items-center gap-3">
                        <div class="h-9 w-9 rounded-xl bg-[#00a884]/20 text-[#00a884] flex items-center justify-center font-bold text-lg">
                            🤖
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-white flex items-center gap-2">
                                Bot Auto-Replies & Ready Triggers
                                <span class="px-2 py-0.5 rounded-full bg-[#00a884]/20 text-[#00a884] text-[10px] font-bold" x-text="(filteredBotReplies ? filteredBotReplies.length : 0) + ' rules'"></span>
                            </h3>
                            <p class="text-[11px] text-[#8696a0]" x-text="activeConversation ? `Trigger automated response to ${activeConversation.name} (${activeConversation.phone || activeConversation.wa_id})` : 'Select a conversation to trigger bot replies'"></p>
                        </div>
                    </div>
                    <button type="button" @click="showBotRepliesModal = false" class="text-[#8696a0] hover:text-white text-lg p-1 rounded-lg transition" title="Close">✕</button>
                </div>

                {{-- Search & Filter Controls --}}
                <div class="p-3 border-b border-[#2a3942] bg-[#111a20] flex items-center gap-2.5 flex-none">
                    <div class="relative flex-1 bg-[#182229] rounded-xl flex items-center px-3 py-2 border border-[#2a3942] focus-within:border-[#00a884] transition">
                        <svg class="h-4 w-4 text-[#8696a0] mr-2 flex-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                        <input type="text" x-model="botSearchQuery" placeholder="Search rules by title, keyword, or text snippet..."
                               class="bg-transparent border-none outline-none text-xs text-white placeholder-[#8696a0] w-full focus:ring-0 p-0">
                        <button x-show="botSearchQuery" @click="botSearchQuery = ''" class="text-[#8696a0] hover:text-white text-xs ml-1">✕</button>
                    </div>
                    <select x-model="botFilterType" class="bg-[#182229] border border-[#2a3942] rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-[#00a884]">
                        <option value="all">All Types</option>
                        <option value="text">Text Only</option>
                        <option value="interactive_buttons">Buttons</option>
                        <option value="image_header">Image Header</option>
                        <option value="template">Meta Template</option>
                    </select>
                </div>

                {{-- Rules Grid/List (Scrollable) --}}
                <div class="flex-1 overflow-y-auto wa-dark-scrollbar p-4 space-y-3">
                    <template x-if="!filteredBotReplies || filteredBotReplies.length === 0">
                        <div class="py-12 text-center text-[#8696a0]">
                            <div class="text-3xl mb-2">🔍</div>
                            <div class="text-white font-semibold mb-1">No Bot Replies Found</div>
                            <p class="text-[11px] max-w-sm mx-auto">No auto-reply rules match your search filter. Try another keyword or create a new rule in settings.</p>
                        </div>
                    </template>

                    <template x-for="rule in (filteredBotReplies || [])" :key="rule.id">
                        <div class="p-3.5 rounded-xl bg-[#111a20] border border-[#2a3942] hover:border-[#00a884]/60 transition space-y-2.5">
                            {{-- Card Header --}}
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="font-bold text-white text-xs" x-text="rule.name"></h4>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase"
                                              :class="{
                                                  'bg-blue-500/20 text-blue-300 border border-blue-500/30': rule.reply_type === 'text',
                                                  'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30': rule.reply_type === 'interactive_buttons',
                                                  'bg-purple-500/20 text-purple-300 border border-purple-500/30': rule.reply_type === 'image_header',
                                                  'bg-amber-500/20 text-amber-300 border border-amber-500/30': rule.reply_type === 'template'
                                              }"
                                              x-text="rule.reply_type.replace('_', ' ')">
                                        </span>
                                        <span x-show="rule.is_handoff" class="px-2 py-0.5 rounded-full bg-rose-500/20 text-rose-300 border border-rose-500/30 text-[10px] font-semibold">
                                            Agent Handoff
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-[10px] text-[#8696a0] mt-1" x-show="rule.keywords">
                                        <span class="font-semibold text-[#00a884]">Triggers:</span>
                                        <span class="font-mono bg-[#182229] px-1.5 py-0.5 rounded border border-[#2a3942]" x-text="rule.keywords"></span>
                                    </div>
                                </div>
                            </div>

                            {{-- Card Body Preview --}}
                            <div class="p-2.5 rounded-lg bg-[#182229] border border-[#2a3942]/80 space-y-2">
                                {{-- Header text/image preview --}}
                                <div x-show="rule.header_text" class="font-bold text-white text-[11px] flex items-center gap-1.5">
                                    <span class="text-[#00a884]">📌</span>
                                    <span x-text="rule.header_text"></span>
                                </div>
                                <div x-show="rule.header_image_url" class="flex items-center gap-2 text-[10px] text-purple-300">
                                    <span>🖼️ Image Header:</span>
                                    <a :href="rule.header_image_url" target="_blank" class="truncate underline hover:text-white" x-text="rule.header_image_url"></a>
                                </div>

                                {{-- Reply Text --}}
                                <div class="text-[#e9edef] text-[11px] whitespace-pre-wrap leading-relaxed" x-text="rule.reply_text"></div>

                                {{-- Interactive Buttons Preview --}}
                                <div x-show="rule.buttons && rule.buttons.length > 0" class="flex flex-wrap gap-1.5 pt-1 border-t border-[#2a3942]/60">
                                    <template x-for="(btn, bIdx) in rule.buttons" :key="bIdx">
                                        <span class="px-2 py-1 rounded bg-[#222e35] text-white text-[10px] font-medium border border-[#2a3942] flex items-center gap-1">
                                            <span>🔘</span>
                                            <span x-text="btn"></span>
                                        </span>
                                    </template>
                                </div>

                                {{-- Footer text --}}
                                <div x-show="rule.footer_text" class="text-[10px] text-[#8696a0] italic" x-text="rule.footer_text"></div>
                            </div>

                            {{-- Card Actions --}}
                            <div class="flex items-center justify-end gap-2 pt-1">
                                {{-- Insert to composer --}}
                                <button type="button" @click="insertBotReplyText(rule.reply_text)"
                                        class="px-3 py-1.5 rounded-lg bg-[#222e35] hover:bg-[#2a3942] text-white font-semibold text-xs transition flex items-center gap-1.5">
                                    <span>✏️</span>
                                    <span>Insert in Composer</span>
                                </button>

                                {{-- Trigger rule immediately --}}
                                <button type="button" @click="triggerBotReply(rule.id)" :disabled="isTriggeringBot"
                                        class="px-3.5 py-1.5 rounded-lg bg-[#00a884] hover:brightness-110 disabled:opacity-40 text-[#0d1418] font-bold text-xs transition flex items-center gap-1.5 shadow-md">
                                    <span x-show="!isTriggeringBot">⚡</span>
                                    <span x-show="isTriggeringBot" class="animate-spin">⏳</span>
                                    <span x-text="isTriggeringBot ? 'Dispatching...' : 'Dispatch Bot Reply'"></span>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Modal Footer --}}
                <div class="p-3 border-t border-[#2a3942] bg-[#111a20] flex items-center justify-between flex-none text-[11px] text-[#8696a0]">
                    <span>💡 Tip: Clicking <b>Dispatch</b> executes the complete rule (including buttons or templates).</span>
                    <a href="{{ route('admin.whatsapp-auto-replies.index') }}" target="_blank" class="text-[#00a884] hover:underline font-semibold flex items-center gap-1">
                        <span>Manage Bot Rules</span>
                        <span>↗</span>
                    </a>
                </div>

            </div>
        </div>
    </template>

</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('whatsappInbox', () => ({
        conversations: @json($conversationsJson),
        filteredConversations: [],
        activeConversation: @json($activeConversationJson),
        messages: @json($initialMessages),
        searchQuery: '{{ addslashes($q) }}',
        filter: 'all',
        showChatInfo: true,
        soundEnabled: true,
        isSending: false,
        isPolling: false,
        attachmentModal: false,
        docType: 'file',
        selectedFile: null,
        selectedFileName: '',
        quotationId: '',
        bookingRef: '',
        docCaption: '',
        templates: @json($templatesJson),
        botReplies: @json($botRepliesJson),
        selectedTemplate: null,
        templateParams: [],
        templateHeaderType: 'none',
        templateHeaderFile: null,
        templateHeaderFileName: '',
        templateHeaderUrl: '',
        templateHeaderDocumentSource: 'file',
        templateHeaderDocumentFile: null,
        templateHeaderDocumentFileName: '',
        templateQuotationId: '',
        templateBookingRef: '',
        templateTextParam: '',
        showBotRepliesModal: false,
        botSearchQuery: '',
        botFilterType: 'all',
        isTriggeringBot: false,
        templateName: '',
        templateParam1: '',
        textMessage: '',
        newNote: '',
        lastMessageId: 0,
        messagePollTimer: null,
        conversationPollTimer: null,

        get filteredBotReplies() {
            let list = [...(this.botReplies || [])];
            if (this.botFilterType && this.botFilterType !== 'all') {
                list = list.filter(r => r.reply_type === this.botFilterType);
            }
            if (this.botSearchQuery && this.botSearchQuery.trim()) {
                const q = this.botSearchQuery.toLowerCase().trim();
                list = list.filter(r =>
                    (r.name && r.name.toLowerCase().includes(q)) ||
                    (r.keywords && r.keywords.toLowerCase().includes(q)) ||
                    (r.reply_text && r.reply_text.toLowerCase().includes(q))
                );
            }
            return list;
        },

        get previewTemplateBody() {
            if (!this.selectedTemplate || !this.selectedTemplate.body_text) return '';
            let text = this.selectedTemplate.body_text;
            if (this.selectedTemplate.body_variables && this.selectedTemplate.body_variables.length > 0) {
                this.selectedTemplate.body_variables.forEach((v, idx) => {
                    const val = this.templateParams[idx] ? this.templateParams[idx].trim() : '';
                    const replacement = val !== '' ? val : `[${v.placeholder}]`;
                    text = text.replaceAll(v.placeholder, replacement);
                });
            }
            return text;
        },

        init() {
            this.filterConversations();

            if (this.messages.length > 0) {
                this.lastMessageId = Math.max(...this.messages.map(m => m.id || 0));
            }

            this.$nextTick(() => {
                this.scrollToBottom(false);
            });

            // Start adaptive polling
            this.startPolling();

            // Auto-pause polling when tab is hidden
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    this.stopPolling();
                } else {
                    this.startPolling();
                    this.syncConversations();
                    this.pollMessages();
                }
            });
        },

        filterConversations() {
            let list = [...this.conversations];

            if (this.searchQuery.trim()) {
                const q = this.searchQuery.toLowerCase();
                list = list.filter(c =>
                    (c.name && c.name.toLowerCase().includes(q)) ||
                    (c.wa_id && c.wa_id.includes(q)) ||
                    (c.last_message_preview && c.last_message_preview.toLowerCase().includes(q))
                );
            }

            if (this.filter === 'unread') {
                list = list.filter(c => c.unread_count > 0);
            } else if (this.filter === 'read') {
                list = list.filter(c => c.unread_count === 0);
            }

            this.filteredConversations = list;
        },

        selectConversation(id) {
            if (this.activeConversation && this.activeConversation.id === id) return;

            const target = this.conversations.find(c => c.id === id);
            if (target) {
                target.unread_count = 0;
            }

            window.history.pushState({}, '', `{{ route('admin.whatsapp.index') }}?c=${id}`);

            fetch(`{{ url('admin/whatsapp') }}/${id}/messages`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.activeConversation = data.conversation;
                    this.messages = data.messages || [];
                    this.lastMessageId = data.last_id || (this.messages.length > 0 ? Math.max(...this.messages.map(m => m.id)) : 0);
                    this.$nextTick(() => this.scrollToBottom(false));
                }
            })
            .catch(err => console.error('Error loading conversation:', err));
        },

        startPolling() {
            this.stopPolling();

            // Every 2s: poll delta for active conversation
            this.messagePollTimer = setInterval(() => {
                this.pollMessages();
            }, 2000);

            // Every 4s: sync sidebar conversations
            this.conversationPollTimer = setInterval(() => {
                this.syncConversations();
            }, 4000);
        },

        stopPolling() {
            if (this.messagePollTimer) clearInterval(this.messagePollTimer);
            if (this.conversationPollTimer) clearInterval(this.conversationPollTimer);
        },

        pollMessages() {
            if (!this.activeConversation || this.isSending) return;

            this.isPolling = true;
            fetch(`{{ url('admin/whatsapp') }}/${this.activeConversation.id}/messages?after_id=${this.lastMessageId}`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(data => {
                this.isPolling = false;
                if (!data.success) return;

                // Apply status updates
                if (data.status_updates && data.status_updates.length > 0) {
                    data.status_updates.forEach(up => {
                        const target = this.messages.find(m => m.id === up.id);
                        if (target) {
                            target.status = up.status;
                            if (up.error) target.error = up.error;
                        }
                    });
                }

                // Append new messages
                if (data.messages && data.messages.length > 0) {
                    let hadInbound = false;
                    data.messages.forEach(newMsg => {
                        if (!this.messages.some(m => m.id === newMsg.id)) {
                            this.messages.push(newMsg);
                            if (newMsg.is_inbound) hadInbound = true;
                        }
                    });

                    this.lastMessageId = Math.max(...this.messages.map(m => m.id || 0));

                    if (hadInbound) {
                        this.playChime();
                    }

                    this.$nextTick(() => this.scrollToBottom(true));
                }

                if (data.conversation) {
                    this.activeConversation.is_window_open = data.conversation.is_window_open;
                    this.activeConversation.window_expires_in = data.conversation.window_expires_in;
                    this.activeConversation.bot_paused = data.conversation.bot_paused;
                }
            })
            .catch(() => { this.isPolling = false; });
        },

        syncConversations() {
            fetch(`{{ route('admin.whatsapp.conversations-sync') }}`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.conversations) {
                    this.conversations = data.conversations;
                    this.filterConversations();
                }
            })
            .catch(err => console.error('Error syncing conversations:', err));
        },

        sendTextMessage() {
            const text = this.textMessage.trim();
            if (!text || !this.activeConversation || this.isSending) return;

            const tempId = 'tmp_' + Date.now();
            const optimisticMsg = {
                id: tempId,
                direction: 'outbound',
                is_inbound: false,
                type: 'text',
                body: text,
                status: 'pending',
                sent_at_time: 'Just now'
            };

            this.messages.push(optimisticMsg);
            this.textMessage = '';
            this.isSending = true;
            this.$nextTick(() => this.scrollToBottom(true));

            fetch(`{{ url('admin/whatsapp') }}/${this.activeConversation.id}/reply`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ body: text })
            })
            .then(res => res.json())
            .then(data => {
                this.isSending = false;
                const idx = this.messages.findIndex(m => m.id === tempId);
                if (data.success && data.message) {
                    if (idx !== -1) this.messages.splice(idx, 1, data.message);
                    if (data.conversation) {
                        this.activeConversation.is_window_open = data.conversation.is_window_open;
                        this.activeConversation.window_expires_in = data.conversation.window_expires_in;
                    }
                    this.syncConversations();
                } else {
                    if (idx !== -1) {
                        this.messages[idx].status = 'failed';
                        this.messages[idx].error = data.error || 'Failed to deliver message.';
                    }
                }
            })
            .catch(() => {
                this.isSending = false;
                const idx = this.messages.findIndex(m => m.id === tempId);
                if (idx !== -1) {
                    this.messages[idx].status = 'failed';
                    this.messages[idx].error = 'Network error.';
                }
            });
        },

        openAttachmentModal(type = 'file') {
            this.docType = type;
            this.attachmentModal = true;
        },

        handleFileSelect(event) {
            const file = event.target.files ? event.target.files[0] : null;
            if (file) {
                this.selectedFile = file;
                const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
                this.selectedFileName = `${file.name} (${sizeMb} MB)`;
            }
        },

        clearFile() {
            this.selectedFile = null;
            this.selectedFileName = '';
            if (this.$refs.directFileInput) {
                this.$refs.directFileInput.value = '';
            }
        },

        sendDocumentMessage() {
            if (!this.activeConversation || this.isSending) return;

            this.isSending = true;
            const formData = new FormData();
            formData.append('doc_type', this.docType);
            formData.append('caption', this.docCaption || '');

            if (this.docType === 'file') {
                if (!this.selectedFile) {
                    alert('Please choose a file or image to attach.');
                    this.isSending = false;
                    return;
                }
                formData.append('file', this.selectedFile);
            } else if (this.docType === 'quotation') {
                if (!this.quotationId) {
                    alert('Please select a quotation proposal.');
                    this.isSending = false;
                    return;
                }
                formData.append('quotation_id', this.quotationId);
            } else {
                if (!this.bookingRef) {
                    alert('Please enter a booking reference.');
                    this.isSending = false;
                    return;
                }
                formData.append('booking_reference', this.bookingRef);
            }

            fetch(`{{ url('admin/whatsapp') }}/${this.activeConversation.id}/document`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                this.isSending = false;
                if (data.success && data.message) {
                    this.messages.push(data.message);
                    this.attachmentModal = false;
                    this.docCaption = '';
                    this.clearFile();
                    this.$nextTick(() => this.scrollToBottom(true));
                    this.syncConversations();
                } else {
                    alert(data.error || 'Failed to send attachment.');
                }
            })
            .catch(err => {
                this.isSending = false;
                alert('Network error sending attachment.');
                console.error(err);
            });
        },

        onTemplateChange() {
            if (!this.templateName) {
                this.selectedTemplate = null;
                this.templateHeaderType = 'none';
                this.templateParams = [];
                return;
            }
            const found = (this.templates || []).find(t => t.name === this.templateName);
            this.selectedTemplate = found || null;
            if (found) {
                this.templateHeaderType = found.header_type || 'none';
                const varCount = found.body_variables ? found.body_variables.length : 0;
                this.templateParams = new Array(varCount).fill('');
                if (varCount > 0 && this.activeConversation) {
                    this.templateParams[0] = this.activeConversation.name || '';
                }
            } else {
                this.templateHeaderType = 'none';
                this.templateParams = [];
            }
            this.clearHeaderImage();
            this.clearHeaderDocument();
            this.templateHeaderUrl = '';
            this.templateQuotationId = '';
            this.templateBookingRef = '';
            this.templateTextParam = '';
        },

        handleHeaderImageSelect(event) {
            const file = event.target.files ? event.target.files[0] : null;
            if (file) {
                this.templateHeaderFile = file;
                const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
                this.templateHeaderFileName = `${file.name} (${sizeMb} MB)`;
            }
        },

        clearHeaderImage() {
            this.templateHeaderFile = null;
            this.templateHeaderFileName = '';
            if (this.$refs.headerImageInput) {
                this.$refs.headerImageInput.value = '';
            }
        },

        handleHeaderDocumentSelect(event) {
            const file = event.target.files ? event.target.files[0] : null;
            if (file) {
                this.templateHeaderDocumentFile = file;
                const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
                this.templateHeaderDocumentFileName = `${file.name} (${sizeMb} MB)`;
            }
        },

        clearHeaderDocument() {
            this.templateHeaderDocumentFile = null;
            this.templateHeaderDocumentFileName = '';
            if (this.$refs.headerDocInput) {
                this.$refs.headerDocInput.value = '';
            }
        },

        triggerBotReply(ruleId) {
            if (!this.activeConversation || this.isTriggeringBot) return;
            this.isTriggeringBot = true;

            fetch(`{{ url('admin/whatsapp') }}/${this.activeConversation.id}/trigger-bot-reply`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ rule_id: ruleId })
            })
            .then(res => res.json())
            .then(data => {
                this.isTriggeringBot = false;
                if (data.success && data.message) {
                    this.messages.push(data.message);
                    this.showBotRepliesModal = false;
                    if (data.conversation) {
                        this.activeConversation.is_window_open = data.conversation.is_window_open;
                        this.activeConversation.window_expires_in = data.conversation.window_expires_in;
                        this.activeConversation.bot_paused = data.conversation.bot_paused;
                    }
                    this.$nextTick(() => this.scrollToBottom(true));
                    this.syncConversations();
                    this.playChime();
                } else {
                    alert(data.error || 'Failed to dispatch bot reply.');
                }
            })
            .catch(err => {
                this.isTriggeringBot = false;
                alert('Network error while triggering bot reply.');
                console.error(err);
            });
        },

        insertBotReplyText(text) {
            if (!text) return;
            this.textMessage = text;
            this.showBotRepliesModal = false;
            this.$nextTick(() => {
                if (this.$refs.textInput) this.$refs.textInput.focus();
            });
        },

        sendTemplateMessage() {
            if (!this.templateName || !this.activeConversation || this.isSending) return;

            // Header media validations
            if (this.templateHeaderType === 'image') {
                if (!this.templateHeaderFile && !this.templateHeaderUrl.trim()) {
                    alert('Please upload an image file or enter a public image URL for this template header.');
                    return;
                }
            } else if (this.templateHeaderType === 'document') {
                if (this.templateHeaderDocumentSource === 'file' && !this.templateHeaderDocumentFile) {
                    alert('Please select a PDF document file to upload for this template header.');
                    return;
                }
                if (this.templateHeaderDocumentSource === 'quotation' && !this.templateQuotationId) {
                    alert('Please select a CRM quotation for this template header.');
                    return;
                }
                if (this.templateHeaderDocumentSource === 'voucher' && !this.templateBookingRef.trim()) {
                    alert('Please enter a booking reference for this template header.');
                    return;
                }
            }

            this.isSending = true;
            const formData = new FormData();
            formData.append('template', this.templateName);
            formData.append('lang', this.selectedTemplate ? this.selectedTemplate.language : 'en_US');
            formData.append('header_type', this.templateHeaderType || 'none');

            if (this.templateHeaderType === 'image') {
                if (this.templateHeaderFile) {
                    formData.append('header_image_file', this.templateHeaderFile);
                } else if (this.templateHeaderUrl.trim()) {
                    formData.append('header_image_url', this.templateHeaderUrl.trim());
                }
            } else if (this.templateHeaderType === 'document') {
                if (this.templateHeaderDocumentSource === 'file' && this.templateHeaderDocumentFile) {
                    formData.append('header_document_file', this.templateHeaderDocumentFile);
                } else if (this.templateHeaderDocumentSource === 'quotation') {
                    formData.append('header_quotation_id', this.templateQuotationId);
                } else if (this.templateHeaderDocumentSource === 'voucher') {
                    formData.append('header_booking_ref', this.templateBookingRef.trim());
                }
            } else if (this.templateHeaderType === 'text') {
                if (this.templateTextParam.trim()) {
                    formData.append('header_text_param', this.templateTextParam.trim());
                }
            }

            if (this.templateParams && this.templateParams.length > 0) {
                this.templateParams.forEach((param, index) => {
                    formData.append(`params[${index}]`, param ?? '');
                });
            }

            fetch(`{{ url('admin/whatsapp') }}/${this.activeConversation.id}/template`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                this.isSending = false;
                if (data.success && data.message) {
                    this.messages.push(data.message);
                    this.attachmentModal = false;
                    this.templateName = '';
                    this.selectedTemplate = null;
                    this.templateParams = [];
                    this.clearHeaderImage();
                    this.clearHeaderDocument();
                    this.$nextTick(() => this.scrollToBottom(true));
                    this.syncConversations();
                    this.playChime();
                } else {
                    alert(data.error || 'Failed to send template.');
                }
            })
            .catch(err => {
                this.isSending = false;
                alert('Network error sending template.');
                console.error(err);
            });
        },

        toggleBot() {
            if (!this.activeConversation) return;

            fetch(`{{ url('admin/whatsapp') }}/${this.activeConversation.id}/toggle-bot`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.activeConversation.bot_paused = data.bot_paused;
                    const c = this.conversations.find(conv => conv.id === this.activeConversation.id);
                    if (c) c.bot_paused = data.bot_paused;
                }
            })
            .catch(err => console.error('Error toggling bot:', err));
        },

        assignStaff(staffId) {
            if (!this.activeConversation) return;

            fetch(`{{ url('admin/whatsapp') }}/${this.activeConversation.id}/assign`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ assigned_to: staffId || null })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.activeConversation.assigned_to = data.assigned_to;
                    this.activeConversation.assignee_name = data.assignee_name;
                }
            })
            .catch(err => console.error('Error assigning agent:', err));
        },

        saveNote() {
            const noteText = this.newNote.trim();
            if (!noteText || !this.activeConversation) return;

            fetch(`{{ url('admin/whatsapp') }}/${this.activeConversation.id}/notes`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ note: noteText })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.note) {
                    if (!this.activeConversation.notes) this.activeConversation.notes = [];
                    this.activeConversation.notes.unshift(data.note);
                    this.newNote = '';
                }
            })
            .catch(err => console.error('Error saving note:', err));
        },

        deleteConversation() {
            if (!this.activeConversation) return;
            if (!confirm(`Are you sure you want to delete conversation with ${this.activeConversation.name}?`)) return;

            const id = this.activeConversation.id;

            fetch(`{{ url('admin/whatsapp') }}/${id}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.conversations = this.conversations.filter(c => c.id !== id);
                    this.filterConversations();
                    this.activeConversation = null;
                    this.messages = [];
                    window.history.pushState({}, '', `{{ route('admin.whatsapp.index') }}`);
                }
            })
            .catch(err => console.error('Error deleting conversation:', err));
        },

        insertCanned(text) {
            this.textMessage = text;
            this.$nextTick(() => {
                if (this.$refs.textInput) this.$refs.textInput.focus();
            });
        },

        scrollToBottom(smooth = true) {
            const container = this.$refs.messagesContainer;
            if (container) {
                container.scrollTo({
                    top: container.scrollHeight,
                    behavior: smooth ? 'smooth' : 'auto'
                });
            }
        },

        toggleSound() {
            this.soundEnabled = !this.soundEnabled;
            if (this.soundEnabled) this.playChime();
        },

        playChime() {
            if (!this.soundEnabled) return;
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (!AudioContext) return;
                const ctx = new AudioContext();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();

                osc.connect(gain);
                gain.connect(ctx.destination);

                osc.type = 'sine';
                const now = ctx.currentTime;
                osc.frequency.setValueAtTime(587.33, now); // D5
                osc.frequency.setValueAtTime(880, now + 0.08); // A5

                gain.gain.setValueAtTime(0.12, now);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.35);

                osc.start(now);
                osc.stop(now + 0.35);
            } catch (e) {
                // AudioContext requires user gesture
            }
        }
    }));
});
</script>
@endpush
@endsection

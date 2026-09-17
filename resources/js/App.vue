<script setup>
import { ref, computed, onMounted, nextTick } from "vue";
import {
    Sun,
    Check,
    Plus,
    Search,
    LayoutDashboard,
    Inbox,
    CalendarDays,
    CircleCheck,
    Folder,
    ArrowUpRight,
    LogOut,
    X,
    Trash2,
    Pencil,
    ArrowRight,
    LoaderCircle,
    Menu,
} from "lucide-vue-next";
const user = ref(null),
    ready = ref(false),
    busy = ref(false),
    error = ref(""),
    tasks = ref([]),
    projects = ref([]),
    view = ref("overview"),
    search = ref(""),
    priority = ref("all"),
    sort = ref("newest"),
    mobile = ref(false);
const authMode = ref("login"),
    auth = ref({
        name: "",
        username: "",
        email: "",
        password: "",
        password_confirmation: "",
    });
const modal = ref(null),
    editing = ref(null),
    task = ref({}),
    project = ref({ name: "", color: "#687b62" }),
    saving = ref(false),
    pending = ref(new Set()),
    confirmDelete = ref(null);
let csrf = document.querySelector('meta[name="csrf-token"]').content;
async function api(path, method = "GET", body) {
    const response = await fetch("/api" + path, {
        method,
        credentials: "same-origin",
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": csrf,
        },
        body: body ? JSON.stringify(body) : undefined,
    });
    const data = response.status === 204 ? null : await response.json();
    if (!response.ok) {
        if (response.status === 401) user.value = null;
        if (response.status === 419) {
            const session = await fetch("/api/session", {
                headers: { Accept: "application/json" },
            }).then((r) => r.json());
            csrf = session.csrf;
        }
        throw new Error(
            data?.errors
                ? Object.values(data.errors).flat().join(" ")
                : response.status === 419
                  ? "Your session expired. Please try again."
                  : data?.message || "Something went wrong. Please try again.",
        );
    }
    if (data?.csrf) csrf = data.csrf;
    return data;
}
async function load() {
    const data = await api("/workspace");
    tasks.value = data.tasks;
    projects.value = data.projects;
}
onMounted(async () => {
    try {
        const data = await api("/session");
        user.value = data.user;
        if (user.value) await load();
    } catch (e) {
        error.value = e.message;
    } finally {
        ready.value = true;
    }
});
async function authenticate() {
    busy.value = true;
    error.value = "";
    try {
        const data = await api("/" + authMode.value, "POST", auth.value);
        user.value = data.user;
        auth.value.password = "";
        auth.value.password_confirmation = "";
        await load();
    } catch (e) {
        error.value = e.message;
    } finally {
        busy.value = false;
    }
}
async function logout() {
    try {
        await api("/logout", "POST");
        user.value = null;
        tasks.value = [];
        projects.value = [];
        view.value = "overview";
    } catch (e) {
        error.value = e.message;
    }
}
const today = new Date().toLocaleDateString("en-CA");
const active = computed(() => tasks.value.filter((t) => !t.completed_at));
const completed = computed(() => tasks.value.filter((t) => t.completed_at));
const dueToday = computed(() =>
    active.value.filter((t) => t.due_date === today),
);
const overdue = computed(() =>
    active.value.filter((t) => t.due_date && t.due_date < today),
);
const selectedProject = computed(() =>
    projects.value.find((p) => view.value === "project-" + p.id),
);
const title = computed(
    () =>
        selectedProject.value?.name ||
        {
            overview: "Your day, with intention.",
            all: "All tasks",
            today: "Focus on today",
            upcoming: "A little further ahead",
            completed: "Look how far you’ve come.",
        }[view.value],
);
const filtered = computed(() => {
    let result = tasks.value.filter((t) =>
        view.value === "completed" ? !!t.completed_at : !t.completed_at,
    );
    if (selectedProject.value)
        result = result.filter(
            (t) => t.project_id === selectedProject.value.id,
        );
    if (view.value === "today")
        result = result.filter((t) => t.due_date && t.due_date <= today);
    if (view.value === "upcoming")
        result = result.filter((t) => t.due_date && t.due_date > today);
    const query = search.value.toLowerCase();
    result = result.filter(
        (t) =>
            (t.title + " " + (t.description || ""))
                .toLowerCase()
                .includes(query) &&
            (priority.value === "all" || t.priority === priority.value),
    );
    return result.sort((a, b) =>
        sort.value === "priority"
            ? { high: 0, medium: 1, low: 2 }[a.priority] -
              { high: 0, medium: 1, low: 2 }[b.priority]
            : sort.value === "due"
              ? (a.due_date || "9999").localeCompare(b.due_date || "9999")
              : b.id - a.id,
    );
});
const progress = computed(() =>
    tasks.value.length
        ? Math.round((completed.value.length / tasks.value.length) * 100)
        : 0,
);
function navigate(value) {
    view.value = value;
    search.value = "";
    priority.value = "all";
    mobile.value = false;
}
function projectFor(id) {
    return projects.value.find((p) => p.id === id);
}
function dateLabel(value) {
    if (!value) return "";
    if (value === today) return "Today";
    return new Date(value + "T12:00:00").toLocaleDateString("en-US", {
        month: "short",
        day: "numeric",
    });
}
async function openTask(value = null) {
    editing.value = value?.id || null;
    task.value = value
        ? {
              title: value.title,
              description: value.description || "",
              priority: value.priority,
              due_date: value.due_date || "",
              project_id: value.project_id || "",
          }
        : {
              title: "",
              description: "",
              priority: "medium",
              due_date: view.value === "today" ? today : "",
              project_id: selectedProject.value?.id || "",
          };
    error.value = "";
    modal.value = "task";
    await nextTick();
    document.getElementById("task-title")?.focus();
}
async function saveTask() {
    saving.value = true;
    error.value = "";
    try {
        const data = await api(
            editing.value ? "/tasks/" + editing.value : "/tasks",
            editing.value ? "PUT" : "POST",
            {
                ...task.value,
                due_date: task.value.due_date || null,
                project_id: task.value.project_id || null,
            },
        );
        if (editing.value)
            tasks.value = tasks.value.map((t) => (t.id === data.id ? data : t));
        else tasks.value.unshift(data);
        modal.value = null;
    } catch (e) {
        error.value = e.message;
    } finally {
        saving.value = false;
    }
}
async function toggle(t) {
    if (pending.value.has(t.id)) return;
    pending.value.add(t.id);
    try {
        const data = await api("/tasks/" + t.id + "/completion", "PATCH", {
            completed: !t.completed_at,
        });
        tasks.value = tasks.value.map((x) => (x.id === data.id ? data : x));
    } catch (e) {
        error.value = e.message;
    } finally {
        pending.value.delete(t.id);
    }
}
async function remove() {
    saving.value = true;
    try {
        const { type, id } = confirmDelete.value;
        await api("/" + type + "/" + id, "DELETE");
        if (type === "tasks")
            tasks.value = tasks.value.filter((t) => t.id !== id);
        else {
            projects.value = projects.value.filter((p) => p.id !== id);
            tasks.value.forEach((t) => {
                if (t.project_id === id) t.project_id = null;
            });
            navigate("all");
        }
        confirmDelete.value = null;
    } catch (e) {
        error.value = e.message;
    } finally {
        saving.value = false;
    }
}
async function saveProject() {
    saving.value = true;
    error.value = "";
    try {
        const data = await api("/projects", "POST", project.value);
        projects.value.push(data);
        modal.value = null;
        navigate("project-" + data.id);
        project.value = { name: "", color: "#687b62" };
    } catch (e) {
        error.value = e.message;
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div v-if="!ready" class="loading">
        <LoaderCircle class="spin" /> Getting your space ready…
    </div>
    <main v-else-if="!user" class="auth-layout">
        <section class="auth-story">
            <a class="brand" href="/"
                ><span class="brand-icon"><Sun :size="23" /></span> daymark<span
                    class="brand-dot"
                    >.</span
                ></a
            >
            <div>
                <span class="eyebrow"
                    >A LITTLE STRUCTURE. A LOT MORE SPACE.</span
                >
                <h1>Make room for<br />what matters.</h1>
                <p>
                    A calmer place for your plans, projects, and the little
                    things that move life forward.
                </p>
                <div class="illustration">
                    <div class="orbit"></div>
                    <div class="sample-card">
                        <span class="eyebrow">ONE THING AT A TIME</span>
                        <p>
                            <span class="sample-check">✓</span> Start somewhere.
                        </p>
                        <p>
                            <span class="sample-check">✓</span> Find your
                            rhythm.
                        </p>
                        <p>
                            <span class="sample-empty"></span> Make it a good
                            day.
                        </p>
                        <div class="sample-line"><span></span></div>
                        <small>Progress, at your own pace.</small>
                    </div>
                    <span class="spark">✳</span>
                </div>
            </div>
            <small>Less overwhelm. More forward.</small>
        </section>
        <section class="auth-form">
            <div class="auth-form-inner">
                <span class="eyebrow">YOUR PERSONAL WORKSPACE</span>
                <h2>
                    {{
                        authMode === "login"
                            ? "Welcome back."
                            : "A fresh start."
                    }}
                </h2>
                <p>
                    {{
                        authMode === "login"
                            ? "Pick up where you left off."
                            : "Create your account and clear a little headspace."
                    }}
                </p>
                <form @submit.prevent="authenticate">
                    <template v-if="authMode === 'register'"
                        ><label
                            >Your name<input
                                v-model="auth.name"
                                required
                                maxlength="80"
                                autocomplete="name"
                                placeholder="Alex Morgan" /></label
                        ><label
                            >Email address<input
                                v-model="auth.email"
                                type="email"
                                required
                                autocomplete="email"
                                placeholder="alex@example.com" /></label></template
                    ><label
                        >Username<input
                            v-model="auth.username"
                            required
                            autocomplete="username"
                            placeholder="Your username"
                            :minlength="authMode === 'register' ? 3 : 1"
                            maxlength="40" /></label
                    ><label
                        >Password<input
                            v-model="auth.password"
                            type="password"
                            required
                            :autocomplete="
                                authMode === 'login'
                                    ? 'current-password'
                                    : 'new-password'
                            "
                            placeholder="Enter your password"
                            :minlength="
                                authMode === 'register' ? 8 : 1
                            " /></label
                    ><template v-if="authMode === 'register'"
                        ><small
                            >At least 8 characters, including a letter and a
                            number.</small
                        ><label
                            >Confirm password<input
                                v-model="auth.password_confirmation"
                                type="password"
                                required
                                autocomplete="new-password"
                                placeholder="One more time" /></label
                    ></template>
                    <p v-if="error" class="error" role="alert">{{ error }}</p>
                    <button class="primary auth-submit" :disabled="busy">
                        {{
                            busy
                                ? "Just a moment…"
                                : authMode === "login"
                                  ? "Sign in"
                                  : "Create account"
                        }}<ArrowRight :size="18" />
                    </button>
                </form>
                <p class="auth-switch">
                    {{
                        authMode === "login"
                            ? "New to Daymark?"
                            : "Already have an account?"
                    }}
                    <button
                        @click="
                            authMode =
                                authMode === 'login' ? 'register' : 'login';
                            error = '';
                        "
                    >
                        {{
                            authMode === "login"
                                ? "Create an account"
                                : "Sign in"
                        }}
                    </button>
                </p>
                <div class="privacy-note">
                    <span class="tiny-dot"></span> Your plans stay in your own
                    private space.
                </div>
            </div>
        </section>
    </main>
    <div v-else class="workspace">
        <div v-if="mobile" class="sidebar-shade" @click="mobile = false"></div>
        <aside class="sidebar" :class="{ open: mobile }">
            <a href="#" @click.prevent="navigate('overview')" class="brand"
                ><span class="brand-icon"><Sun :size="22" /></span> daymark<span
                    class="brand-dot"
                    >.</span
                ></a
            >
            <div class="workspace-label">PERSONAL WORKSPACE</div>
            <nav>
                <button
                    v-for="item in [
                        {
                            id: 'overview',
                            label: 'Overview',
                            icon: LayoutDashboard,
                        },
                        { id: 'all', label: 'All tasks', icon: Inbox },
                        { id: 'today', label: 'Today', icon: Sun },
                        {
                            id: 'upcoming',
                            label: 'Upcoming',
                            icon: CalendarDays,
                        },
                        {
                            id: 'completed',
                            label: 'Completed',
                            icon: CircleCheck,
                        },
                    ]"
                    :key="item.id"
                    :class="{ selected: view === item.id }"
                    @click="navigate(item.id)"
                >
                    <component :is="item.icon" :size="18" />{{ item.label
                    }}<span v-if="item.id === 'all'" class="nav-count">{{
                        active.length
                    }}</span
                    ><span
                        v-if="item.id === 'today' && dueToday.length"
                        class="nav-count"
                        >{{ dueToday.length }}</span
                    >
                </button>
            </nav>
            <div class="project-heading">
                PROJECTS<button
                    title="Add project"
                    aria-label="Add project"
                    @click="
                        modal = 'project';
                        error = '';
                    "
                >
                    <Plus :size="17" />
                </button>
            </div>
            <nav class="project-nav">
                <button
                    v-for="p in projects"
                    :key="p.id"
                    :class="{ selected: view === 'project-' + p.id }"
                    @click="navigate('project-' + p.id)"
                >
                    <span
                        class="project-dot"
                        :style="{ background: p.color }"
                    ></span
                    ><span class="project-name">{{ p.name }}</span
                    ><span class="nav-count">{{
                        active.filter((t) => t.project_id === p.id).length
                    }}</span></button
                ><button
                    class="add-project"
                    @click="
                        modal = 'project';
                        error = '';
                    "
                >
                    <Plus :size="16" /> New project
                </button>
            </nav>
            <div class="sidebar-bottom">
                <div class="slow-note">
                    <Sun :size="24" />
                    <p>Small steps.<br /><strong>Good things ahead.</strong></p>
                </div>
                <div class="profile">
                    <span class="avatar">{{
                        user.name.slice(0, 1).toUpperCase()
                    }}</span>
                    <div>
                        <strong>{{ user.name }}</strong
                        ><small>@{{ user.username }}</small>
                    </div>
                    <button
                        @click="logout"
                        title="Sign out"
                        aria-label="Sign out"
                    >
                        <LogOut :size="17" />
                    </button>
                </div>
            </div>
        </aside>
        <div class="main-shell">
            <header class="topbar">
                <button
                    class="mobile-menu icon-button"
                    @click="mobile = true"
                    aria-label="Open navigation"
                >
                    <Menu /></button
                ><span class="breadcrumb"
                    >My workspace <span>/</span>
                    <strong>{{
                        selectedProject?.name ||
                        {
                            overview: "Overview",
                            all: "All tasks",
                            today: "Today",
                            upcoming: "Upcoming",
                            completed: "Completed",
                        }[view]
                    }}</strong></span
                ><span class="header-date">{{
                    new Date().toLocaleDateString("en-US", {
                        weekday: "short",
                        month: "short",
                        day: "numeric",
                    })
                }}</span>
            </header>
            <main class="content">
                <div class="page-heading">
                    <div>
                        <span class="eyebrow">{{
                            view === "overview"
                                ? "LET’S MAKE A LITTLE PROGRESS"
                                : "A PLACE FOR EVERY NEXT STEP"
                        }}</span>
                        <h1>{{ title }}</h1>
                        <p>
                            {{
                                view === "overview"
                                    ? `Welcome back, ${user.name.split(" ")[0]}. You’ve got this.`
                                    : view === "completed"
                                      ? "Every checkmark is a step forward."
                                      : "Clear your head. Put it on the list."
                            }}
                        </p>
                    </div>
                    <div class="heading-actions">
                        <button
                            v-if="selectedProject"
                            class="icon-button"
                            aria-label="Delete project"
                            @click="
                                confirmDelete = {
                                    type: 'projects',
                                    id: selectedProject.id,
                                }
                            "
                        >
                            <Trash2 :size="18" /></button
                        ><button class="primary" @click="openTask()">
                            <Plus :size="18" /> Add task
                        </button>
                    </div>
                </div>
                <div v-if="error && !modal" class="error" role="alert">
                    {{ error
                    }}<button @click="error = ''" aria-label="Dismiss error">
                        <X :size="16" />
                    </button>
                </div>
                <section v-if="view === 'overview'" class="stats">
                    <button class="stat-card" @click="navigate('all')">
                        <span class="stat-label"
                            ><span class="stat-icon sage"
                                ><Inbox :size="18"
                            /></span>
                            In progress <ArrowUpRight :size="15" /></span
                        ><strong>{{
                            active.length.toString().padStart(2, "0")
                        }}</strong
                        ><small>A little closer with every step</small></button
                    ><button class="stat-card" @click="navigate('today')">
                        <span class="stat-label"
                            ><span class="stat-icon sand"
                                ><Sun :size="18"
                            /></span>
                            Due today <ArrowUpRight :size="15" /></span
                        ><strong>{{
                            dueToday.length.toString().padStart(2, "0")
                        }}</strong
                        ><small>{{
                            overdue.length
                                ? `${overdue.length} overdue · time for a fresh plan`
                                : "Your focus for the here and now"
                        }}</small></button
                    ><button class="stat-card" @click="navigate('completed')">
                        <span class="stat-label"
                            ><span class="stat-icon lilac"
                                ><CircleCheck :size="18"
                            /></span>
                            Completed <ArrowUpRight :size="15" /></span
                        ><strong
                            >{{ completed.length.toString().padStart(2, "0")
                            }}<span class="progress-caption"
                                >{{ progress }}% of all tasks</span
                            ></strong
                        >
                        <div class="progress-track">
                            <span :style="{ width: progress + '%' }"></span>
                        </div>
                    </button>
                </section>
                <section class="task-panel">
                    <div class="section-heading">
                        <div>
                            <h2>
                                {{
                                    view === "overview"
                                        ? "Your task list"
                                        : view === "completed"
                                          ? "Completed tasks"
                                          : "Tasks"
                                }}
                                <span>{{ filtered.length }}</span>
                            </h2>
                            <p>
                                {{
                                    view === "overview"
                                        ? "Big plans start with small actions."
                                        : "A little clarity goes a long way."
                                }}
                            </p>
                        </div>
                        <select v-model="sort" aria-label="Sort tasks">
                            <option value="newest">Newest first</option>
                            <option value="due">Due date</option>
                            <option value="priority">Priority</option>
                        </select>
                    </div>
                    <div class="toolbar">
                        <div class="search-box">
                            <Search :size="17" /><input
                                v-model="search"
                                placeholder="Find a task…"
                                aria-label="Search tasks"
                            /><button
                                v-if="search"
                                @click="search = ''"
                                aria-label="Clear search"
                            >
                                <X :size="15" />
                            </button>
                        </div>
                        <div class="priority-filter">
                            <span>Priority</span
                            ><select
                                v-model="priority"
                                aria-label="Filter priority"
                            >
                                <option value="all">All priorities</option>
                                <option value="high">High</option>
                                <option value="medium">Medium</option>
                                <option value="low">Low</option>
                            </select>
                        </div>
                    </div>
                    <div class="list-labels">
                        <span>TASK</span><span>PRIORITY</span
                        ><span>DUE DATE</span><span></span>
                    </div>
                    <div v-if="!filtered.length" class="empty">
                        <div class="empty-icon"><Check :size="28" /></div>
                        <h3>
                            {{
                                search || priority !== "all"
                                    ? "No matching tasks"
                                    : view === "completed"
                                      ? "Your wins will live here"
                                      : "A little room to begin."
                            }}
                        </h3>
                        <p>
                            {{
                                search || priority !== "all"
                                    ? "Try another search or priority."
                                    : "Add your next step, and make it happen at your pace."
                            }}
                        </p>
                        <button
                            v-if="
                                !search &&
                                priority === 'all' &&
                                view !== 'completed'
                            "
                            class="text-button"
                            @click="openTask()"
                        >
                            <Plus :size="16" /> Create a task
                        </button>
                    </div>
                    <article
                        v-for="t in filtered"
                        :key="t.id"
                        class="task-row"
                        :class="{ done: t.completed_at }"
                    >
                        <div class="task-main">
                            <button
                                class="checkbox"
                                :class="{ checked: t.completed_at }"
                                :disabled="pending.has(t.id)"
                                :aria-label="
                                    (t.completed_at ? 'Reopen ' : 'Complete ') +
                                    t.title
                                "
                                @click="toggle(t)"
                            >
                                <Check
                                    v-if="t.completed_at"
                                    :size="14"
                                /></button
                            ><button class="task-info" @click="openTask(t)">
                                <strong>{{ t.title }}</strong
                                ><span
                                    ><span
                                        v-if="projectFor(t.project_id)"
                                        class="task-project"
                                        ><span
                                            class="project-dot"
                                            :style="{
                                                background: projectFor(
                                                    t.project_id,
                                                ).color,
                                            }"
                                        ></span
                                        >{{
                                            projectFor(t.project_id).name
                                        }}</span
                                    ><span v-else>Personal</span
                                    ><span
                                        v-if="t.description"
                                        class="task-description"
                                    >
                                        · {{ t.description }}</span
                                    ></span
                                >
                            </button>
                        </div>
                        <span class="badge" :class="t.priority"
                            ><span></span>{{ t.priority }}</span
                        ><span
                            class="due"
                            :class="{
                                overdue:
                                    t.due_date &&
                                    t.due_date < today &&
                                    !t.completed_at,
                            }"
                            ><CalendarDays v-if="t.due_date" :size="13" />{{
                                dateLabel(t.due_date) || "No date"
                            }}</span
                        >
                        <div class="row-actions">
                            <button @click="openTask(t)" aria-label="Edit task">
                                <Pencil :size="15" /></button
                            ><button
                                @click="
                                    confirmDelete = { type: 'tasks', id: t.id }
                                "
                                aria-label="Delete task"
                            >
                                <Trash2 :size="15" />
                            </button>
                        </div>
                    </article>
                    <button class="add-row" @click="openTask()">
                        <Plus :size="17" /> Add a new task<span
                            >A little less on your mind.</span
                        >
                    </button>
                </section>
                <footer class="page-footer">
                    <span
                        ><span class="tiny-dot"></span> A little progress is
                        still progress.</span
                    ><span>MAKE SPACE. MAKE PROGRESS.</span>
                </footer>
            </main>
        </div>
    </div>
    <div
        v-if="modal"
        class="modal-backdrop"
        @click.self="!saving && (modal = null)"
        @keydown.esc="!saving && (modal = null)"
    >
        <section
            class="modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="dialog-title"
        >
            <button
                class="modal-close"
                @click="modal = null"
                :disabled="saving"
                aria-label="Close dialog"
            >
                <X :size="20" /></button
            ><span class="eyebrow">MAKE A LITTLE SPACE</span>
            <h2 id="dialog-title">
                {{
                    modal === "task"
                        ? editing
                            ? "Edit your task"
                            : "One small next step."
                        : "A home for your next idea."
                }}
            </h2>
            <form v-if="modal === 'task'" @submit.prevent="saveTask">
                <label
                    >Task name<input
                        id="task-title"
                        v-model="task.title"
                        required
                        maxlength="200"
                        placeholder="What would you like to do?" /></label
                ><label
                    >Notes <span class="optional">optional</span
                    ><textarea
                        v-model="task.description"
                        rows="3"
                        maxlength="10000"
                        placeholder="A few details to help you get started…"
                    ></textarea>
                </label>
                <div class="form-columns">
                    <label
                        >Priority<select
                            v-model="task.priority"
                            aria-label="Priority"
                        >
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select></label
                    ><label
                        >Due date<input v-model="task.due_date" type="date"
                    /></label>
                </div>
                <label
                    >Project<select
                        v-model="task.project_id"
                        aria-label="Project"
                    >
                        <option value="">Personal · no project</option>
                        <option v-for="p in projects" :key="p.id" :value="p.id">
                            {{ p.name }}
                        </option>
                    </select></label
                >
                <p v-if="error" class="error" role="alert">{{ error }}</p>
                <div class="modal-footer">
                    <button
                        type="button"
                        class="secondary"
                        @click="modal = null"
                        :disabled="saving"
                    >
                        Cancel</button
                    ><button class="primary" :disabled="saving">
                        {{
                            saving
                                ? "Saving…"
                                : editing
                                  ? "Save changes"
                                  : "Create task"
                        }}<ArrowRight :size="16" />
                    </button>
                </div>
            </form>
            <form v-else @submit.prevent="saveProject">
                <label
                    >Project name<input
                        v-model="project.name"
                        required
                        maxlength="80"
                        placeholder="Something worth working on"
                        autofocus /></label
                ><label
                    >Project color<input
                        v-model="project.color"
                        type="color"
                        class="color-input"
                /></label>
                <p v-if="error" class="error" role="alert">{{ error }}</p>
                <div class="modal-footer">
                    <button
                        type="button"
                        class="secondary"
                        @click="modal = null"
                    >
                        Cancel</button
                    ><button class="primary" :disabled="saving">
                        {{ saving ? "Creating…" : "Create project"
                        }}<Plus :size="16" />
                    </button>
                </div>
            </form>
        </section>
    </div>
    <div v-if="confirmDelete" class="modal-backdrop">
        <section
            class="modal small-modal"
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="delete-title"
        >
            <h2 id="delete-title">
                Delete this
                {{ confirmDelete.type === "tasks" ? "task" : "project" }}?
            </h2>
            <p>
                {{
                    confirmDelete.type === "tasks"
                        ? "This task will be permanently removed."
                        : "Tasks in this project will move to Personal."
                }}
            </p>
            <p v-if="error" class="error">{{ error }}</p>
            <div class="modal-footer">
                <button
                    class="secondary"
                    @click="confirmDelete = null"
                    :disabled="saving"
                >
                    Keep it</button
                ><button class="danger" @click="remove" :disabled="saving">
                    {{ saving ? "Deleting…" : "Delete" }}
                </button>
            </div>
        </section>
    </div>
</template>

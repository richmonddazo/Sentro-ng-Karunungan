import React, { useEffect, useMemo, useState } from "react";
import { createRoot } from "react-dom/client";
import {
  BookOpen, Home, Search, Heart, Bookmark, User, Download,
  Library, LogOut, Menu, X, Bell, Settings, ChevronRight
} from "lucide-react";
import "./index.css";

/*
 * BOOKHOLD — React migration
 * --------------------------
 * The original uploaded application was a single HTML/CSS/Vanilla-JS file.
 * This version moves the application shell and screen navigation to React.
 * Existing localStorage-based behavior can be migrated screen-by-screen without
 * changing the visual/data intent of the original project.
 */

const STORAGE = {
  user: "bookhold_user",
  activeScreen: "bookhold_active_screen",
  wishlist: "bookhold_wishlist",
  reservations: "bookhold_reservations",
  downloads: "bookhold_downloads",
};

const sampleBooks = [
  { id: 1, title: "The Great Gatsby", author: "F. Scott Fitzgerald", genre: "Classic", available: true, cover: "https://covers.openlibrary.org/b/isbn/9780743273565-M.jpg" },
  { id: 2, title: "To Kill a Mockingbird", author: "Harper Lee", genre: "Classic", available: true, cover: "https://covers.openlibrary.org/b/isbn/9780061120084-M.jpg" },
  { id: 3, title: "Harry Potter and the Sorcerer's Stone", author: "J.K. Rowling", genre: "Fantasy", available: false, cover: "https://covers.openlibrary.org/b/isbn/9780590353427-M.jpg" },
  { id: 4, title: "Pride and Prejudice", author: "Jane Austen", genre: "Romance", available: true, cover: "https://covers.openlibrary.org/b/isbn/9780141439518-M.jpg" },
  { id: 5, title: "The Hobbit", author: "J.R.R. Tolkien", genre: "Fantasy", available: true, cover: "https://covers.openlibrary.org/b/isbn/9780547928227-M.jpg" },
  { id: 6, title: "The Little Prince", author: "Antoine de Saint-Exupéry", genre: "Children's Book", available: true, cover: "https://covers.openlibrary.org/b/isbn/9780156012195-M.jpg" },
];

const genres = [
  "All", "Fiction", "Non-Fiction", "Classic", "Fantasy", "Romance",
  "Science", "History", "Biography", "Children's Book"
];

function load(key, fallback) {
  try {
    const value = localStorage.getItem(key);
    return value ? JSON.parse(value) : fallback;
  } catch {
    return fallback;
  }
}

function save(key, value) {
  localStorage.setItem(key, JSON.stringify(value));
}

function App() {
  const [user, setUser] = useState(() => load(STORAGE.user, null));
  const [screen, setScreen] = useState(() => localStorage.getItem(STORAGE.activeScreen) || "dashboard");
  const [query, setQuery] = useState("");
  const [genre, setGenre] = useState("All");
  const [wishlist, setWishlist] = useState(() => load(STORAGE.wishlist, []));
  const [reservations, setReservations] = useState(() => load(STORAGE.reservations, []));
  const [downloads, setDownloads] = useState(() => load(STORAGE.downloads, []));
  const [selectedBook, setSelectedBook] = useState(null);
  const [menuOpen, setMenuOpen] = useState(false);

  useEffect(() => save(STORAGE.wishlist, wishlist), [wishlist]);
  useEffect(() => save(STORAGE.reservations, reservations), [reservations]);
  useEffect(() => save(STORAGE.downloads, downloads), [downloads]);
  useEffect(() => localStorage.setItem(STORAGE.activeScreen, screen), [screen]);

  const filteredBooks = useMemo(() => {
    const q = query.trim().toLowerCase();
    return sampleBooks.filter(book => {
      const matchesQuery = !q || `${book.title} ${book.author} ${book.genre}`.toLowerCase().includes(q);
      const matchesGenre = genre === "All" || book.genre === genre;
      return matchesQuery && matchesGenre;
    });
  }, [query, genre]);

  const toggleWishlist = (book) => {
    setWishlist(prev =>
      prev.some(b => b.id === book.id)
        ? prev.filter(b => b.id !== book.id)
        : [...prev, book]
    );
  };

  const reserveBook = (book) => {
    if (!book.available || reservations.some(r => r.id === book.id)) return;
    setReservations(prev => [...prev, { ...book, reservedAt: new Date().toISOString(), status: "Reserved" }]);
    setSelectedBook(null);
  };

  const downloadBook = (book) => {
    if (!downloads.some(b => b.id === book.id)) {
      setDownloads(prev => [...prev, book]);
    }
  };

  const logout = () => {
    setUser(null);
    localStorage.removeItem(STORAGE.user);
    setScreen("login");
  };

  if (!user) {
    return <Auth onLogin={(nextUser) => {
      setUser(nextUser);
      save(STORAGE.user, nextUser);
      setScreen("dashboard");
    }} />;
  }

  const nav = [
    ["dashboard", "Dashboard", Home],
    ["discover", "Discover Books", Search],
    ["reservations", "Reservations", Bookmark],
    ["downloads", "Downloads", Download],
    ["wishlist", "Wishlist", Heart],
    ["genres", "Genres", Library],
    ["profile", "Profile", User],
  ];

  return (
    <div className="bookhold-app">
      <header className="app-header">
        <button className="mobile-menu" onClick={() => setMenuOpen(v => !v)} aria-label="Menu">
          {menuOpen ? <X /> : <Menu />}
        </button>
        <div className="brand" onClick={() => setScreen("dashboard")}>
          <div className="brand-icon"><BookOpen size={22}/></div>
          <span>BOOKHOLD</span>
        </div>
        <div className="header-actions">
          <button className="icon-btn" aria-label="Notifications"><Bell size={20}/></button>
          <button className="avatar" onClick={() => setScreen("profile")}>
            {(user.name || "U").charAt(0).toUpperCase()}
          </button>
        </div>
      </header>

      <div className="app-layout">
        <aside className={`sidebar ${menuOpen ? "open" : ""}`}>
          <div className="sidebar-user">
            <div className="avatar large">{(user.name || "U").charAt(0).toUpperCase()}</div>
            <div>
              <strong>{user.name || "Reader"}</strong>
              <small>{user.email || "BOOKHOLD member"}</small>
            </div>
          </div>

          <nav>
            {nav.map(([id, label, Icon]) => (
              <button key={id} className={screen === id ? "active" : ""} onClick={() => { setScreen(id); setMenuOpen(false); }}>
                <Icon size={19}/><span>{label}</span>
              </button>
            ))}
          </nav>

          <button className="logout-btn" onClick={logout}><LogOut size={19}/> Sign out</button>
        </aside>

        <main className="main-content">
          {screen === "dashboard" && (
            <Dashboard user={user} books={sampleBooks} reservations={reservations} wishlist={wishlist}
              onNavigate={setScreen} onSelect={setSelectedBook}/>
          )}
          {screen === "discover" && (
            <Discover books={filteredBooks} query={query} setQuery={setQuery} genre={genre} setGenre={setGenre}
              wishlist={wishlist} onWishlist={toggleWishlist} onSelect={setSelectedBook}/>
          )}
          {screen === "reservations" && <Collection title="My Reservations" items={reservations} empty="No reservations yet." onSelect={setSelectedBook}/>}
          {screen === "downloads" && <Collection title="My Downloads" items={downloads} empty="No downloaded books yet." onSelect={setSelectedBook}/>}
          {screen === "wishlist" && <Collection title="My Wishlist" items={wishlist} empty="Your wishlist is empty." onSelect={setSelectedBook}/>}
          {screen === "genres" && (
            <Genres genres={genres.slice(1)} books={sampleBooks} onSelectGenre={(g) => { setGenre(g); setScreen("discover"); }}/>
          )}
          {screen === "profile" && <Profile user={user} onLogout={logout}/>}
        </main>
      </div>

      <nav className="bottom-nav">
        {nav.slice(0, 5).map(([id, label, Icon]) => (
          <button key={id} className={screen === id ? "active" : ""} onClick={() => setScreen(id)}>
            <Icon size={19}/><span>{label}</span>
          </button>
        ))}
      </nav>

      {selectedBook && (
        <BookModal book={selectedBook}
          wished={wishlist.some(b => b.id === selectedBook.id)}
          reserved={reservations.some(b => b.id === selectedBook.id)}
          onClose={() => setSelectedBook(null)}
          onWishlist={() => toggleWishlist(selectedBook)}
          onReserve={() => reserveBook(selectedBook)}
          onDownload={() => downloadBook(selectedBook)}
        />
      )}
    </div>
  );
}

function Auth({ onLogin }) {
  const [mode, setMode] = useState("login");
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");

  const submit = (e) => {
    e.preventDefault();
    onLogin({ name: name || email.split("@")[0] || "Reader", email });
  };

  return (
    <div className="auth-screen">
      <div className="auth-card">
        <div className="brand auth-brand"><div className="brand-icon"><BookOpen size={22}/></div><span>BOOKHOLD</span></div>
        <h1>{mode === "login" ? "Welcome back" : "Create your account"}</h1>
        <p>{mode === "login" ? "Sign in to continue to your library." : "Join BOOKHOLD and reserve books online."}</p>
        <form onSubmit={submit}>
          {mode === "register" && <label>Name<input value={name} onChange={e => setName(e.target.value)} required /></label>}
          <label>Email<input type="email" value={email} onChange={e => setEmail(e.target.value)} required /></label>
          <label>Password<input type="password" value={password} onChange={e => setPassword(e.target.value)} required /></label>
          <button className="primary-btn" type="submit">{mode === "login" ? "Sign In" : "Register"}</button>
        </form>
        <button className="text-btn" onClick={() => setMode(mode === "login" ? "register" : "login")}>
          {mode === "login" ? "Create an account" : "Already have an account? Sign in"}
        </button>
      </div>
    </div>
  );
}

function Dashboard({ user, books, reservations, wishlist, onNavigate, onSelect }) {
  return (
    <section>
      <div className="page-heading">
        <div><p className="eyebrow">BOOKHOLD</p><h1>Hello, {user.name || "Reader"}!</h1><p>Discover, reserve, and keep track of your books.</p></div>
      </div>

      <div className="stats-grid">
        <Stat label="Available Books" value={books.filter(b => b.available).length} icon={<BookOpen/>}/>
        <Stat label="Reservations" value={reservations.length} icon={<Bookmark/>}/>
        <Stat label="Wishlist" value={wishlist.length} icon={<Heart/>}/>
        <Stat label="Downloads" value={0} icon={<Download/>}/>
      </div>

      <div className="section-heading"><h2>Featured Books</h2><button className="text-link" onClick={() => onNavigate("discover")}>View all <ChevronRight size={16}/></button></div>
      <BookGrid books={books.slice(0, 4)} onSelect={onSelect}/>
    </section>
  );
}

function Stat({label, value, icon}) {
  return <div className="stat-card"><div className="stat-icon">{icon}</div><div><strong>{value}</strong><span>{label}</span></div></div>;
}

function Discover({ books, query, setQuery, genre, setGenre, wishlist, onWishlist, onSelect }) {
  return (
    <section>
      <div className="page-heading"><p className="eyebrow">LIBRARY</p><h1>Discover Books</h1><p>Search and explore books available in BOOKHOLD.</p></div>
      <div className="search-row">
        <div className="search-box"><Search size={19}/><input value={query} onChange={e => setQuery(e.target.value)} placeholder="Search by title, author, or genre..." /></div>
        <select value={genre} onChange={e => setGenre(e.target.value)}>{genres.map(g => <option key={g}>{g}</option>)}</select>
      </div>
      <BookGrid books={books} wishlist={wishlist} onWishlist={onWishlist} onSelect={onSelect}/>
    </section>
  );
}

function BookGrid({ books, wishlist = [], onWishlist = () => {}, onSelect }) {
  if (!books.length) return <div className="empty-state"><BookOpen size={42}/><h3>No books found</h3><p>Try another search or genre.</p></div>;
  return <div className="book-grid">{books.map(book => (
    <article className="book-card" key={book.id} onClick={() => onSelect(book)}>
      <div className="cover-wrap">
        <img src={book.cover} alt={book.title}/>
        <button className="heart-btn" onClick={e => { e.stopPropagation(); onWishlist(book); }}>
          <Heart size={18} fill={wishlist.some(b => b.id === book.id) ? "currentColor" : "none"}/>
        </button>
      </div>
      <div className="book-info"><span className="book-genre">{book.genre}</span><h3>{book.title}</h3><p>{book.author}</p><span className={book.available ? "availability available" : "availability unavailable"}>{book.available ? "Available" : "Currently unavailable"}</span></div>
    </article>
  ))}</div>;
}

function Collection({ title, items, empty, onSelect }) {
  return <section><div className="page-heading"><p className="eyebrow">MY LIBRARY</p><h1>{title}</h1></div>{items.length ? <BookGrid books={items} onSelect={onSelect}/> : <div className="empty-state"><BookOpen size={42}/><h3>{empty}</h3><p>Explore Discover Books to get started.</p></div>}</section>;
}

function Genres({ genres: list, books, onSelectGenre }) {
  return <section><div className="page-heading"><p className="eyebrow">EXPLORE</p><h1>Genres</h1><p>Browse books by category.</p></div><div className="genre-grid">{list.map(g => <button className="genre-card" key={g} onClick={() => onSelectGenre(g)}><div className="genre-icon"><BookOpen/></div><strong>{g}</strong><span>{books.filter(b => b.genre === g).length} books</span></button>)}</div></section>;
}

function Profile({ user, onLogout }) {
  return <section><div className="page-heading"><p className="eyebrow">ACCOUNT</p><h1>Profile</h1></div><div className="profile-card"><div className="avatar huge">{(user.name || "U").charAt(0).toUpperCase()}</div><h2>{user.name}</h2><p>{user.email}</p><div className="profile-actions"><button><Settings size={18}/> Account Settings</button><button onClick={onLogout}><LogOut size={18}/> Sign out</button></div></div></section>;
}

function BookModal({ book, wished, reserved, onClose, onWishlist, onReserve, onDownload }) {
  return <div className="modal-backdrop" onClick={onClose}><div className="book-modal" onClick={e => e.stopPropagation()}>
    <button className="modal-close" onClick={onClose}><X/></button>
    <img src={book.cover} alt={book.title}/>
    <div className="modal-details"><span className="book-genre">{book.genre}</span><h2>{book.title}</h2><p className="author">{book.author}</p><p>This book is part of the BOOKHOLD collection. Use the actions below to manage it.</p>
      <div className="modal-actions">
        <button onClick={onWishlist}><Heart size={18} fill={wished ? "currentColor" : "none"}/>{wished ? "Remove from Wishlist" : "Add to Wishlist"}</button>
        <button onClick={onDownload}><Download size={18}/> Download</button>
        <button className="primary-btn" disabled={!book.available || reserved} onClick={onReserve}>{reserved ? "Reserved" : book.available ? "Reserve Book" : "Unavailable"}</button>
      </div>
    </div>
  </div></div>;
}

createRoot(document.getElementById("root")).render(<App />);

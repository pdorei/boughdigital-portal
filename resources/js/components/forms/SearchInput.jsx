import React from 'react';
import { Search } from 'lucide-react';

export default function SearchInput({ value, onChange, placeholder = 'Search', label = 'Search' }) {
    return (
        <label className="field-label filter-search-label">
            <span>{label}</span>
            <span className="search-box">
                <Search size={17} aria-hidden="true" />
                <input type="search" placeholder={placeholder} value={value ?? ''} onChange={(event) => onChange(event.target.value)} />
            </span>
        </label>
    );
}

import React from 'react';
import { Briefcase, Building2, Globe, Mail, MapPin, Pencil, Phone, User } from 'lucide-react';
import DisplayField from '@/components/forms/DisplayField';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import Modal from '@/components/ui/Modal';
import { useWorkspace } from '@/hooks/useWorkspace';
import { optionLabel } from '@/utils/options';
import { SOCIAL_NETWORKS } from '@/pages/clients/components/competitors/socialNetworks';

export default function CompetitorDetailsModal({ competitor, onClose, onEdit }) {
    const { options } = useWorkspace();
    const socialLinks = SOCIAL_NETWORKS.filter(({ key }) => competitor.social_media?.[key]);

    return (
        <Modal title="View competitor" onClose={onClose} wide>
            <div className="form-stack">
                {competitor.logo_url ? <img className="competitor-logo-large" src={competitor.logo_url} alt={`${competitor.competitor_name} logo`} /> : null}
                <div className="form-row">
                    <DisplayField icon={Building2} label="Competitor name">{competitor.competitor_name}</DisplayField>
                    <DisplayField icon={Globe} label="Website / domain">
                        <a className="link" href={competitor.website_url} target="_blank" rel="noopener noreferrer">{competitor.competitor_domain}</a>
                    </DisplayField>
                </div>
                <div className="form-row">
                    <DisplayField label="Competitor type">{competitor.competitor_type ? optionLabel(options.competitor_types, competitor.competitor_type) : 'Not specified'}</DisplayField>
                    <DisplayField label="Tier">
                        <Badge status={competitor.tier === 'primary' ? 'active' : 'default'}>{optionLabel(options.competitor_tiers, competitor.tier)}</Badge>
                    </DisplayField>
                </div>
                <div className="form-row">
                    <DisplayField icon={Briefcase} label="Industry">{competitor.industry || 'Not set'}</DisplayField>
                    <DisplayField icon={MapPin} label="Location">{competitor.location || 'Not set'}</DisplayField>
                </div>
                <DisplayField label="Description" multiline>{competitor.description || 'No description provided.'}</DisplayField>
                <DisplayField label="Notes" multiline>{competitor.notes || 'No notes.'}</DisplayField>
                <div className="form-row">
                    <DisplayField icon={User} label="Contact name">{competitor.contact?.name || 'Not set'}</DisplayField>
                    <DisplayField icon={Mail} label="Contact email">
                        {competitor.contact?.email ? <a className="link" href={`mailto:${competitor.contact.email}`}>{competitor.contact.email}</a> : 'Not set'}
                    </DisplayField>
                </div>
                <div className="form-row">
                    <DisplayField icon={Phone} label="Contact phone">{competitor.contact?.phone || 'Not set'}</DisplayField>
                </div>
                <DisplayField label="Social media">
                    {socialLinks.length ? (
                        <span className="inline-actions">
                            {socialLinks.map(({ key, label }) => (
                                <a key={key} className="link" href={competitor.social_media[key]} target="_blank" rel="noopener noreferrer">{label}</a>
                            ))}
                        </span>
                    ) : 'No profiles added.'}
                </DisplayField>
                <DisplayField label="Keywords">
                    {competitor.keywords.length ? (
                        <span className="tag-list">
                            {competitor.keywords.map((keyword) => <span className="tag-chip tag-chip-static" key={keyword}>{keyword}</span>)}
                        </span>
                    ) : 'No keywords added.'}
                </DisplayField>
                {competitor.can?.update ? (
                    <div className="inline-actions action-group-end">
                        <Button icon={Pencil} variant="secondary" onClick={() => onEdit(competitor)}>Edit competitor</Button>
                    </div>
                ) : null}
            </div>
        </Modal>
    );
}

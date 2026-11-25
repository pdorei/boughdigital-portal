import React, { useState } from 'react';
import { Gauge, Save } from 'lucide-react';
import Input from '@/components/forms/Input';
import Select from '@/components/forms/Select';
import Textarea from '@/components/forms/Textarea';
import Button from '@/components/ui/Button';
import ErrorState from '@/components/ui/ErrorState';
import Modal from '@/components/ui/Modal';
import { addArticleScore } from '@/api/content';
import { useWorkspace } from '@/hooks/useWorkspace';

/**
 * Records a score someone has already worked out. Nothing is calculated here:
 * there is no AI scoring engine yet.
 */
export default function ScoreFormModal({ client, article, onClose, onSaved }) {
    const { options, workspaceSlug } = useWorkspace();
    const categories = options.article_score_categories || [];
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState({});
    const [formError, setFormError] = useState(null);

    async function handleSubmit(event) {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});
        setFormError(null);

        const form = new FormData(event.currentTarget);
        const payload = {
            score_type: form.get('score_type'),
            overall_score: form.get('overall_score'),
            category_scores: Object.fromEntries(categories.map(({ value }) => [value, form.get(`category_${value}`) || null])),
            feedback: form.get('feedback') || null,
        };

        try {
            const response = await addArticleScore(workspaceSlug, client.id, article.id, payload);
            onSaved(response.data);
        } catch (saveError) {
            setErrors(saveError.errors);
            setFormError(saveError.message);
            setSubmitting(false);
        }
    }

    return (
        <Modal title="Add score" onClose={onClose} busy={submitting} wide>
            <form className="form-stack" onSubmit={handleSubmit}>
                {formError ? <ErrorState title="Could not save score" copy={formError} /> : null}
                <div className="form-row">
                    <Select name="score_type" label="Score type *" defaultValue="human" options={options.article_score_types || []} error={errors.score_type?.[0]} />
                    <Input icon={Gauge} name="overall_score" type="number" min="0" max="100" label="Overall score (0–100) *" error={errors.overall_score?.[0]} required />
                </div>
                <div className="form-row">
                    {categories.map(({ value, label }) => (
                        <Input key={value} icon={Gauge} name={`category_${value}`} type="number" min="0" max="100" label={label} error={errors[`category_scores.${value}`]?.[0]} />
                    ))}
                </div>
                <Textarea name="feedback" label="Feedback" rows={5} error={errors.feedback?.[0]} />
                <div className="inline-actions action-group-end">
                    <Button variant="secondary" onClick={onClose} disabled={submitting}>Cancel</Button>
                    <Button icon={Save} type="submit" disabled={submitting}>{submitting ? 'Saving...' : 'Save score'}</Button>
                </div>
            </form>
        </Modal>
    );
}

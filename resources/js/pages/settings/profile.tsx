import { Form, Head, useForm, usePage } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import type { FormEvent } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { update } from '@/routes/notifications';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { Auth, NotificationChannel } from '@/types';

type PageProps = {
    auth: Auth;
};

export default function Profile({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { auth } = usePage<PageProps>().props;

    const notificationsForm = useForm<{
        notification_channel: NotificationChannel;
        phone: string;
    }>({
        notification_channel: auth.user.notification_channel,
        phone: auth.user.phone ?? '',
    });

    const isSmsChannel = notificationsForm.data.notification_channel !== 'mail';

    function submitNotificationPreferences(event: FormEvent) {
        event.preventDefault();

        notificationsForm.patch(update.url(), {
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title="Profile settings" />

            <h1 className="sr-only">Profile settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Profile"
                    description="Update your name and email address"
                />

                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>

                                <Input
                                    id="name"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.name}
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder="Full name"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>

                                <Input
                                    id="email"
                                    type="email"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.email}
                                    name="email"
                                    required
                                    autoComplete="username"
                                    placeholder="Email address"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.email}
                                />
                            </div>

                            {mustVerifyEmail &&
                                auth.user.email_verified_at === null && (
                                    <div>
                                        <p className="-mt-4 text-sm text-muted-foreground">
                                            Your email address is unverified.{' '}
                                            <Link
                                                href={send()}
                                                as="button"
                                                className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                            >
                                                Click here to re-send the
                                                verification email.
                                            </Link>
                                        </p>

                                        {status ===
                                            'verification-link-sent' && (
                                            <div className="mt-2 text-sm font-medium text-green-600">
                                                A new verification link has been
                                                sent to your email address.
                                            </div>
                                        )}
                                    </div>
                                )}

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    Save
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Notification preferences"
                    description="Choose how we contact you about your orders."
                />

                <form
                    onSubmit={submitNotificationPreferences}
                    className="space-y-6"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="notification_channel">
                            Notification channel
                        </Label>

                        <Select
                            value={notificationsForm.data.notification_channel}
                            onValueChange={(value) =>
                                notificationsForm.setData(
                                    'notification_channel',
                                    value as NotificationChannel,
                                )
                            }
                        >
                            <SelectTrigger
                                id="notification_channel"
                                className="mt-1 w-full"
                            >
                                <SelectValue placeholder="Select a channel" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="mail">Email</SelectItem>
                                <SelectItem value="sms">SMS</SelectItem>
                                <SelectItem value="both">
                                    Email &amp; SMS
                                </SelectItem>
                            </SelectContent>
                        </Select>

                        <InputError
                            className="mt-2"
                            message={
                                notificationsForm.errors.notification_channel
                            }
                        />
                    </div>

                    {isSmsChannel && (
                        <div className="grid gap-2">
                            <Label htmlFor="phone">Phone number</Label>

                            <Input
                                id="phone"
                                type="tel"
                                className="mt-1 block w-full"
                                value={notificationsForm.data.phone}
                                onChange={(event) =>
                                    notificationsForm.setData(
                                        'phone',
                                        event.target.value,
                                    )
                                }
                                required
                                autoComplete="tel"
                                placeholder="Phone number"
                            />

                            <InputError
                                className="mt-2"
                                message={notificationsForm.errors.phone}
                            />
                        </div>
                    )}

                    <div className="flex items-center gap-4">
                        <Button
                            disabled={notificationsForm.processing}
                            data-test="update-notifications-button"
                        >
                            Save
                        </Button>
                    </div>
                </form>
            </div>

            <DeleteUser />
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Profile settings',
            href: edit(),
        },
    ],
};

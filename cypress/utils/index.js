import { User } from '@nextcloud/cypress'

export const randHash = () => Math.random().toString(36).replace(/[^a-z]+/g, '').slice(0, 10)
export const randUser = () => new User(randHash(), randHash())

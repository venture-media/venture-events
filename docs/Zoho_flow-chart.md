Invoice customer identity v0.9.21.1

                  ┌──────────────────────┐
                  │ Ticket paid → create │
                  │     Zoho invoice     │
                  └───────────┬──────────┘
                              │
                              ▼
                 ┌────────────────────────┐
                 │ Read billing_company,  │
                 │ first_name, last_name, │
                 │    accounting_email    │
                 └────────────┬───────────┘
                              │
                              ▼
                 ╭────────────────────────╮
                 │ billing_company blank? │
                 ╰────────────┬───────────╯
              ┌───────────────┴──────────────┐
              ▼Yes                           ▼No
┌──────────────────────────┐   ┌──────────────────────────┐
│ base_name = First + Last │   │       base_name =        │
│   identity = personal    │   │ billing_company identity │
└─────────────┬────────────┘   │        = company         │
              │                └─────────────┬────────────┘
              └───────────────┬──────────────┘
                              ▼
                ┌──────────────────────────┐
                │ desired_name = WEB_POS / │
                │        base_name         │
                └─────────────┬────────────┘
                              │
                              ▼
                ┌──────────────────────────┐
                │ Look up existing WEB_POS │
                │     contact for this     │
                │      identity only       │
                └─────────────┬────────────┘
                              │
                              ▼
                  ╭──────────────────────╮
                  │ Exact contact_name = │
                  │    desired_name?     ├───────────────────────────┐
                  ╰───────────┬──────────╯                           │
                              └──┐                                   │
                                 ▼No                                 │
                    ╭─────────────────────────╮                      │
                    │   Email probe found a   │                      │
                    │ contact with exact same ├──────────────────────┤
                    │      desired_name?      │                      │
                    ╰────────────┬────────────╯                      │
                                 └─────┐                             │
                                       ▼No                           │
                         ╭──────────────────────────╮                │
                         │  Search by base_name /   │                │
                         │ company_name found exact │                │
                         │      desired_name?       │                │
                         ╰─────────────┬────────────╯                │
                          ┌────────────┴────────────┐                │
                          ▼Yes                      ▼No              │
               ┌────────────────────┐   ┌──────────────────────┐ Yes │
               │ Reuse that contact ├◄──│ No matching identity │─────┴┐
               └────────────────────┘   └───────────┬──────────┘      │
                                       ┌────────────┘                 │
                                       ▼                              │
                          ┌─────────────────────────┐                 │
                          │ Create NEW Zoho contact │                 │
                          └────────────┬────────────┘                 │
                                       │                              │
                                       ▼                              │
                         ┌──────────────────────────┐                 │
                         │ contact_name = WEB_POS / │                 │
                         │ base_name company_name = │                 │
                         │    base_name email =     │                 │
                         │     accounting_email     │                 │
                         └─────────────┬────────────┘                 │
                                       │                              │
                                       ▼                              │
                          ┌────────────────────────┐                  │
                          │ Update billing address │                  │
                          │   from this checkout   │◄─────────────────┘
                          └────────────┬───────────┘
                                       │
                                       ▼
                          ┌─────────────────────────┐
                          │ Upsert contact person = │
                          │      ticket holder      │
                          └────────────┬────────────┘
                                       │
                                       ▼
                          ┌────────────────────────┐
                          │ Create invoice on this │
                          │        contact         │
                          └────────────┬───────────┘
                                       │
                                       ▼
                             ┌──────────────────┐
                             │ Email invoice to │
                             │ accounting_email │
                             └──────────────────┘
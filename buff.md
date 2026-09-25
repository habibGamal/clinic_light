the following are some rules that should applied in the project business logic:
- shift has many expenses and many patient visits and many payments
- in filament we cannot CRUD expenses
- to create patient visit it should belong to currently opened shift
- we cannot edit or delete or cancel  patient visit if no opened shift
- in reception user can control payments and make refunds
- expenses can be only edited/deleted within the same shift
- expenses in old shifts can't be edited or deleted
- to create expenses / payment / refund / cancel patient visit , the shift should be opened
- make a view button for patient visit in reception that show all details of the visit
- after the patient visit is completed or cancelled cannot be edited or deleted
- if the service choosen in the patient visit has a report it should be disabled for deletion in reception (note that there is a bug in this point when report created for one service then we add another service in this form it recreate all services from scratch this cause the report to be deleted)



- remove all existing reports (delete them)
- build (with filament) a component called shift filter , this filter choose multible shifts (with select input) or choose a period of shifts (start/end dates) with some presets (today, yesterday, this week, this month, this year) and custom selection this component will return shift ids for the reports to make thier queries
- make shifts report  (it will use shift filter), it has flash cards for 
    - number of total visits
    - number of completed visits
    - number of wating visits
    - number of cancelled visits
    - total payments for the selected shifts (one card for all payment methods but show detials with small text bottom)
    - total refunds for the selected shifts (one card for all payment methods but show detials with small text bottom)
    - total expenses for the selected shifts 
    - number of new patients for the selected shifts
    - number of new referrals for the selected shifts
    - table of visits (with view button to view details) for the selected shifts
    - table of expenses for the selected shifts
    
- it auto select currently opened shift if exists
- use filament components (tables, forms, filters, cards, flash cards, charts)

- build doctor referrals report (it will use shift filter App\Filament\Filters\ShiftFilter) (hint: you can look at shifts report to learn how to build report)
    - number of total referrals (all doctors combined)
    - number of new patients from referrals (all doctors combined)
    - total payments for the referrals (all doctors combined)
    - total refunds for the referrals (all doctors combined)
    - number of referrals for each doctor (in a table)
        - doctor name
        - number of referrals
        - number of completed visits
        - number of wating visits
        - number of cancelled visits
        - number of new refferrals
        - total payments for the referrals
        - total refunds for the referrals
        - percent of referrals
        - view button that take us to a page that 
            - has a patients table
            - has a patient vists table
    
    - chart of total referrals for each doctor (in a bar chart)

- build services report (it will use shift filter App\Filament\Filters\ShiftFilter) (hint: you can look at shifts report to learn how to build report)
    - top service performed flash card (service name, number of performed )
    - chart to compare services performance (in a bar chart)
    - table of services for the selected shifts
        - name of service
        - number of performed
        - number of completed visits
        - number of wating visits
        - number of cancelled visits
        - total payments for the services
        - total refunds for the services
        - percent of services
        - view button that take us to a page that 
            - has a patient vists table

some other technical rules:
- inertia already serialize the ORM objects no need for manual mapping in the controllers
- always use laravel best practices